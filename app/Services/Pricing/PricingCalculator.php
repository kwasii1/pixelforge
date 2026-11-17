<?php

namespace App\Services\Pricing;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;

/**
 * Turns token counts into money. Every amount is an integer number of nanodollars.
 */
final readonly class PricingCalculator
{
    private const TOKENS_PER_RATE_UNIT = 1_000_000;

    private const BASIS_POINTS = 10_000;

    /**
     * The markup is an exact fraction (5/4 is +25%) so pricing never uses floats.
     */
    public function __construct(
        private int $markupNumerator = 5,
        private int $markupDenominator = 4,
    ) {}

    /**
     * What Pixelforge charges for the given usage at the given rates.
     *
     * Rates are per million tokens, so the sum is divided once at the end. At
     * Pixelforge's published precision the division is exact; should rates ever
     * have more decimals than nanodollars can carry, it rounds up.
     */
    public function providerCost(TokenUsage $usage, Rates $rates): int
    {
        $costPerMillion = $usage->inputText * $rates->inputTextPerMillion
            + $usage->inputImage * $rates->inputImagePerMillion
            + $usage->output * $rates->outputPerMillion
            + $usage->reasoning * $rates->reasoningPerMillion;

        return self::divideRoundingUp($costPerMillion, self::TOKENS_PER_RATE_UNIT);
    }

    /**
     * The cost of one kind of token, for showing a breakdown. The total comes from providerCost().
     */
    public function lineCost(int $tokens, int $ratePerMillion): int
    {
        return self::divideRoundingUp($tokens * $ratePerMillion, self::TOKENS_PER_RATE_UNIT);
    }

    /**
     * What the user pays for a provider cost: the cost plus the markup, rounded up to the nanodollar.
     */
    public function userPrice(int $providerCost): int
    {
        return self::divideRoundingUp($providerCost * $this->markupNumerator, $this->markupDenominator);
    }

    /**
     * The cheapest and most expensive this generation can turn out, before it runs.
     *
     * Text tokens are about one per four characters, give or take the published
     * variance, and reasoning tokens vary within a range set by quality. Image
     * and output tokens are exact.
     */
    public function estimate(
        PricingTable $pricing,
        string $prompt,
        ImageSize $size,
        ImageQuality $quality,
        int $referenceImageCount,
    ): CostEstimate {
        $estimatedTextTokens = self::divideRoundingUp(mb_strlen($prompt), $pricing->charactersPerTextToken);
        $variance = $pricing->textTokenVarianceBasisPoints;
        $imageTokens = $referenceImageCount * $pricing->tokensPerReferenceImage;
        $outputTokens = $pricing->outputTokens($quality, $size);
        $reasoningTokens = $pricing->reasoningTokens($quality);

        $minimumUsage = new TokenUsage(
            inputText: intdiv($estimatedTextTokens * (self::BASIS_POINTS - $variance), self::BASIS_POINTS),
            inputImage: $imageTokens,
            output: $outputTokens,
            reasoning: $reasoningTokens['min'],
        );

        $maximumUsage = new TokenUsage(
            inputText: self::divideRoundingUp($estimatedTextTokens * (self::BASIS_POINTS + $variance), self::BASIS_POINTS),
            inputImage: $imageTokens,
            output: $outputTokens,
            reasoning: $reasoningTokens['max'],
        );

        $minimumCost = $this->providerCost($minimumUsage, $pricing->rates);
        $maximumCost = $this->providerCost($maximumUsage, $pricing->rates);

        return new CostEstimate(
            minimumUsage: $minimumUsage,
            maximumUsage: $maximumUsage,
            minimumCost: $minimumCost,
            maximumCost: $maximumCost,
            minimumPrice: $this->userPrice($minimumCost),
            maximumPrice: $this->userPrice($maximumCost),
        );
    }

    private static function divideRoundingUp(int $dividend, int $divisor): int
    {
        return intdiv($dividend + $divisor - 1, $divisor);
    }
}
