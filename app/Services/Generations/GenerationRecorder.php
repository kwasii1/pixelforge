<?php

namespace App\Services\Generations;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Pixelforge\GenerationData;
use App\Services\Pricing\PricingCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

/**
 * The single place that changes a generation's status and records what it cost.
 *
 * Webhooks, polling and the submit job all report what Pixelforge says through
 * here. Each call locks the row, so concurrent reports are applied one at a
 * time, and the status only ever moves forward. A generation is priced on the
 * one transition into succeeded, so it can never be charged twice.
 */
class GenerationRecorder
{
    public function __construct(private readonly PricingCalculator $calculator) {}

    /**
     * Apply a generation object from Pixelforge (an API response or a webhook event).
     */
    public function applyProviderState(Generation $generation, GenerationData $data): Generation
    {
        return DB::transaction(function () use ($generation, $data): Generation {
            $locked = $this->lock($generation);

            $locked->pixelforge_id ??= $data->id;
            $locked->submitted_at ??= $data->createdAt ?? now();
            $locked->started_at ??= $data->startedAt;

            if ($data->imageUrl !== null) {
                $locked->image_url = $data->imageUrl;
                $locked->image_url_expires_at = $data->imageUrlExpiresAt;
            }

            if ($this->accepts($locked, $data->status)) {
                match ($data->status) {
                    GenerationStatus::Succeeded => $this->recordSuccess($locked, $data),
                    GenerationStatus::Failed => $this->recordFailure($locked, $data->errorCode ?? 'internal_error', $data->errorMessage, $data->completedAt),
                    default => $locked->status = $data->status,
                };
            }

            $locked->save();

            return $generation->setRawAttributes($locked->getAttributes(), sync: true);
        });
    }

    /**
     * Fail a generation that Pixelforge never accepted (rejected at submission, or never reachable). It is free.
     */
    public function markRejected(Generation $generation, string $errorCode, string $message): Generation
    {
        return DB::transaction(function () use ($generation, $errorCode, $message): Generation {
            $locked = $this->lock($generation);

            if (! $locked->status->isFinal() && ! $locked->isKnownToPixelforge()) {
                $this->recordFailure($locked, $errorCode, $message, now());
                $locked->save();
            }

            return $generation->setRawAttributes($locked->getAttributes(), sync: true);
        });
    }

    /**
     * Whether Pixelforge's status should replace ours.
     *
     * Normally only forward moves count. The exception is a generation we failed
     * locally before learning its Pixelforge id: if Pixelforge reports it after
     * all, the request did go through, and Pixelforge's state is the truth.
     */
    private function accepts(Generation $generation, GenerationStatus $next): bool
    {
        if ($generation->status->canTransitionTo($next)) {
            return true;
        }

        return $generation->status === GenerationStatus::Failed
            && $generation->getOriginal('pixelforge_id') === null
            && $next !== GenerationStatus::Failed;
    }

    private function recordSuccess(Generation $generation, GenerationData $data): void
    {
        $rates = $data->rates();

        if ($data->usage === null || $rates === null) {
            throw new UnexpectedValueException("Pixelforge reported generation [{$data->id}] as succeeded without usage or rates.");
        }

        $providerCost = $this->calculator->providerCost($data->usage, $rates);

        $generation->status = GenerationStatus::Succeeded;
        $generation->usage = $data->usage->toArray();
        $generation->rates = $data->rates;
        $generation->provider_cost = $providerCost;
        $generation->price = $this->calculator->userPrice($providerCost);
        $generation->completed_at = $data->completedAt ?? now();
        $generation->error_code = null;
        $generation->error_message = null;
    }

    private function recordFailure(Generation $generation, string $errorCode, ?string $message, ?CarbonImmutable $completedAt): void
    {
        $generation->status = GenerationStatus::Failed;
        $generation->provider_cost = 0;
        $generation->price = 0;
        $generation->error_code = $errorCode;
        $generation->error_message = $message;
        $generation->completed_at = $completedAt ?? now();
    }

    private function lock(Generation $generation): Generation
    {
        return Generation::query()->whereKey($generation->getKey())->lockForUpdate()->firstOrFail();
    }
}
