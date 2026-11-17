<?php

namespace App\Services\Pricing;

use App\Support\Money;

/**
 * Pixelforge token rates, held as nanodollars per one million tokens.
 */
final readonly class Rates
{
    public function __construct(
        public int $inputTextPerMillion,
        public int $inputImagePerMillion,
        public int $outputPerMillion,
        public int $reasoningPerMillion,
    ) {}

    /**
     * @param  array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string}  $rates
     */
    public static function fromArray(array $rates): self
    {
        return new self(
            inputTextPerMillion: Money::fromDecimal($rates['input_text_tokens']),
            inputImagePerMillion: Money::fromDecimal($rates['input_image_tokens']),
            outputPerMillion: Money::fromDecimal($rates['output_tokens']),
            reasoningPerMillion: Money::fromDecimal($rates['reasoning_tokens']),
        );
    }
}
