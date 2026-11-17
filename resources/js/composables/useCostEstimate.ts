import type { MaybeRefOrGetter } from 'vue';
import { computed, toValue } from 'vue';
import type { Pricing } from '@/types/generations';

const TOKENS_PER_RATE_UNIT = 1_000_000;
const BASIS_POINTS = 10_000;

type Usage = {
    inputText: number;
    inputImage: number;
    output: number;
    reasoning: number;
};

type EstimateInput = {
    prompt: string;
    size: string;
    quality: string;
    referenceImageCount: number;
};

const divideRoundingUp = (dividend: number, divisor: number): number =>
    Math.floor((dividend + divisor - 1) / divisor);

/**
 * The live price range for the generate form, in nanodollars.
 *
 * A browser copy of App\Services\Pricing\PricingCalculator::estimate. All
 * values stay integers well below 2^53, so the result matches the server's.
 * The server recalculates on submit, and that is the estimate it stores.
 */
export function useCostEstimate(
    pricing: MaybeRefOrGetter<Pricing>,
    input: MaybeRefOrGetter<EstimateInput>,
) {
    return computed(() => {
        const table = toValue(pricing);
        const { prompt, size, quality, referenceImageCount } = toValue(input);
        const rates = table.ratesPerMillion;

        const estimatedTextTokens = divideRoundingUp(
            Array.from(prompt).length,
            table.charactersPerTextToken,
        );
        const variance = table.textTokenVarianceBasisPoints;
        const reasoning = table.reasoningTokens[quality] ?? { min: 0, max: 0 };

        const common = {
            inputImage: referenceImageCount * table.tokensPerReferenceImage,
            output: table.outputTokens[quality]?.[size] ?? 0,
        };

        const minimum: Usage = {
            ...common,
            inputText: Math.floor(
                (estimatedTextTokens * (BASIS_POINTS - variance)) /
                    BASIS_POINTS,
            ),
            reasoning: reasoning.min,
        };

        const maximum: Usage = {
            ...common,
            inputText: divideRoundingUp(
                estimatedTextTokens * (BASIS_POINTS + variance),
                BASIS_POINTS,
            ),
            reasoning: reasoning.max,
        };

        const providerCost = (usage: Usage): number =>
            divideRoundingUp(
                usage.inputText * rates.inputText +
                    usage.inputImage * rates.inputImage +
                    usage.output * rates.output +
                    usage.reasoning * rates.reasoning,
                TOKENS_PER_RATE_UNIT,
            );

        const userPrice = (cost: number): number =>
            divideRoundingUp(
                cost * table.markup.numerator,
                table.markup.denominator,
            );

        return {
            minimum,
            maximum,
            minimumPrice: userPrice(providerCost(minimum)),
            maximumPrice: userPrice(providerCost(maximum)),
        };
    });
}
