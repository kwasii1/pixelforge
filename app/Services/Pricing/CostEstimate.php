<?php

namespace App\Services\Pricing;

use App\Support\Money;

/**
 * The range a generation can cost before it runs. All amounts are nanodollars.
 */
final readonly class CostEstimate
{
    public function __construct(
        public TokenUsage $minimumUsage,
        public TokenUsage $maximumUsage,
        public int $minimumCost,
        public int $maximumCost,
        public int $minimumPrice,
        public int $maximumPrice,
    ) {}

    /**
     * @return array{minimum_price: string, maximum_price: string, minimum_price_display: string, maximum_price_display: string, minimum_usage: array<string, int>, maximum_usage: array<string, int>}
     */
    public function toArray(): array
    {
        return [
            'minimum_price' => Money::toDecimal($this->minimumPrice),
            'maximum_price' => Money::toDecimal($this->maximumPrice),
            'minimum_price_display' => Money::format($this->minimumPrice),
            'maximum_price_display' => Money::format($this->maximumPrice),
            'minimum_usage' => $this->minimumUsage->toArray(),
            'maximum_usage' => $this->maximumUsage->toArray(),
        ];
    }
}
