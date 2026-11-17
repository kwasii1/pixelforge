<?php

namespace App\Services\Pricing;

final readonly class TokenUsage
{
    public function __construct(
        public int $inputText,
        public int $inputImage,
        public int $output,
        public int $reasoning,
    ) {}

    /**
     * @param  array{input_text_tokens: int, input_image_tokens: int, output_tokens: int, reasoning_tokens: int}  $usage
     */
    public static function fromArray(array $usage): self
    {
        return new self(
            inputText: (int) $usage['input_text_tokens'],
            inputImage: (int) $usage['input_image_tokens'],
            output: (int) $usage['output_tokens'],
            reasoning: (int) $usage['reasoning_tokens'],
        );
    }

    public function total(): int
    {
        return $this->inputText + $this->inputImage + $this->output + $this->reasoning;
    }

    /**
     * @return array{input_text_tokens: int, input_image_tokens: int, output_tokens: int, reasoning_tokens: int, total_tokens: int}
     */
    public function toArray(): array
    {
        return [
            'input_text_tokens' => $this->inputText,
            'input_image_tokens' => $this->inputImage,
            'output_tokens' => $this->output,
            'reasoning_tokens' => $this->reasoning,
            'total_tokens' => $this->total(),
        ];
    }
}
