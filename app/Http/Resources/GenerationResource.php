<?php

namespace App\Http\Resources;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Pricing\PricingCalculator;
use App\Services\Pricing\Rates;
use App\Services\Pricing\TokenUsage;
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
            'costBreakdown' => $this->costBreakdown(),
            'imageUrl' => $this->status === GenerationStatus::Succeeded ? $this->image_url : null,
            'imageUrlExpiresAt' => $this->image_url_expires_at?->toIso8601ZuluString(),
            'imageExpired' => $this->image_url_expires_at?->isPast() ?? false,
            'failureReason' => $this->failureReason(),
            'errorCode' => $this->error_code,
            'createdAt' => $this->created_at?->toIso8601ZuluString(),
            'completedAt' => $this->completed_at?->toIso8601ZuluString(),
        ];
    }

    /**
     * Tokens × rate for each kind of token, then the total, the markup, and what was charged.
     *
     * @return array{lines: list<array{label: string, tokens: int, ratePerMillion: string, cost: array{exact: string, display: string}}>, providerCost: array{exact: string, display: string}, markup: array{exact: string, display: string}, price: array{exact: string, display: string}}|null
     */
    private function costBreakdown(): ?array
    {
        if ($this->usage === null || $this->rates === null || $this->provider_cost === null || $this->price === null) {
            return null;
        }

        $usage = TokenUsage::fromArray($this->usage);
        $rates = Rates::fromArray($this->rates);
        $calculator = app(PricingCalculator::class);

        $lines = [
            ['Prompt text', $usage->inputText, $rates->inputTextPerMillion, 'input_text_tokens'],
            ['Reference images', $usage->inputImage, $rates->inputImagePerMillion, 'input_image_tokens'],
            ['Image output', $usage->output, $rates->outputPerMillion, 'output_tokens'],
            ['Reasoning', $usage->reasoning, $rates->reasoningPerMillion, 'reasoning_tokens'],
        ];

        return [
            'lines' => array_map(fn (array $line): array => [
                'label' => $line[0],
                'tokens' => $line[1],
                'ratePerMillion' => $this->rates[$line[3]],
                'cost' => $this->money($calculator->lineCost($line[1], $line[2])),
            ], $lines),
            'providerCost' => $this->money($this->provider_cost),
            'markup' => $this->money($this->price - $this->provider_cost),
            'price' => $this->money($this->price),
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
