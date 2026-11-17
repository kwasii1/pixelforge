<?php

namespace App\Http\Resources;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Generation
 */
class GenerationResource extends JsonResource
{
    /**
     * Money goes out as exact nine-place strings plus a short display form, never as floats.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prompt' => $this->prompt,
            'size' => $this->size->value,
            'sizeLabel' => $this->size->label(),
            'quality' => $this->quality->value,
            'qualityLabel' => $this->quality->label(),
            'referenceImages' => $this->reference_images,
            'status' => $this->status->value,
            'statusLabel' => $this->statusLabel(),
            'isFinal' => $this->status->isFinal(),
            'estimate' => [
                'min' => $this->money($this->estimated_min_price),
                'max' => $this->money($this->estimated_max_price),
            ],
            'price' => $this->price === null ? null : $this->money($this->price),
            'providerCost' => $this->provider_cost === null ? null : $this->money($this->provider_cost),
            'usage' => $this->usage,
            'rates' => $this->rates,
            'imageUrl' => $this->status === GenerationStatus::Succeeded ? $this->image_url : null,
            'imageUrlExpiresAt' => $this->image_url_expires_at?->toIso8601ZuluString(),
            'imageExpired' => $this->image_url_expires_at?->isPast() ?? false,
            'failureReason' => $this->failureReason(),
            'errorCode' => $this->error_code,
            'createdAt' => $this->created_at?->toIso8601ZuluString(),
            'completedAt' => $this->completed_at?->toIso8601ZuluString(),
        ];
    }

    private function statusLabel(): string
    {
        return $this->resource->isWaitingForCapacity()
            ? 'Waiting for capacity'
            : $this->status->label();
    }

    /**
     * @return array{exact: string, display: string}
     */
    private function money(int $nanodollars): array
    {
        return [
            'exact' => Money::toDecimal($nanodollars),
            'display' => Money::format($nanodollars),
        ];
    }
}
