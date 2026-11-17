<?php

namespace App\Services\Pricing;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;

/**
 * The published pricing and token tables from GET /v1/pricing.
 */
final readonly class PricingTable
{
    /**
     * @param  array<string, array<string, int>>  $outputTokens  Keyed by quality, then size.
     * @param  array<string, array{min: int, max: int}>  $reasoningTokens  Keyed by quality.
     */
    public function __construct(
        public Rates $rates,
        public int $charactersPerTextToken,
        public int $textTokenVarianceBasisPoints,
        public int $tokensPerReferenceImage,
        public array $outputTokens,
        public array $reasoningTokens,
    ) {}

    /**
     * Built from the decoded GET /v1/pricing body, which is external data and read defensively.
     *
     * @param  array<string, mixed>  $pricing
     */
    public static function fromArray(array $pricing): self
    {
        $tokens = $pricing['tokens'];

        return new self(
            rates: Rates::fromArray($pricing['rates']),
            charactersPerTextToken: (int) $tokens['characters_per_text_token'],
            textTokenVarianceBasisPoints: (int) round((float) $tokens['text_token_variance'] * 10_000),
            tokensPerReferenceImage: (int) $tokens['per_reference_image'],
            outputTokens: $tokens['output'],
            reasoningTokens: $tokens['reasoning'],
        );
    }

    public function outputTokens(ImageQuality $quality, ImageSize $size): int
    {
        return (int) $this->outputTokens[$quality->value][$size->value];
    }

    /**
     * @return array{min: int, max: int}
     */
    public function reasoningTokens(ImageQuality $quality): array
    {
        return [
            'min' => (int) $this->reasoningTokens[$quality->value]['min'],
            'max' => (int) $this->reasoningTokens[$quality->value]['max'],
        ];
    }
}
