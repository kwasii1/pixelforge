<?php

namespace App\Services\Pixelforge;

use App\Enums\GenerationStatus;
use App\Services\Pricing\Rates;
use App\Services\Pricing\TokenUsage;
use Carbon\CarbonImmutable;

/**
 * A Pixelforge generation object, as returned by the API and inside webhook events.
 */
final readonly class GenerationData
{
    /**
     * @param  array<string, string>  $metadata
     * @param  array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string}|null  $rates
     */
    public function __construct(
        public string $id,
        public GenerationStatus $status,
        public array $metadata,
        public ?string $imageUrl,
        public ?CarbonImmutable $imageUrlExpiresAt,
        public ?TokenUsage $usage,
        public ?array $rates,
        public ?string $errorCode,
        public ?string $errorMessage,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $completedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $generation
     */
    public static function fromArray(array $generation): self
    {
        return new self(
            id: (string) $generation['id'],
            status: GenerationStatus::from((string) $generation['status']),
            metadata: (array) ($generation['metadata'] ?? []),
            imageUrl: $generation['image_url'] ?? null,
            imageUrlExpiresAt: self::date($generation['image_url_expires_at'] ?? null),
            usage: isset($generation['usage']) ? TokenUsage::fromArray($generation['usage']) : null,
            rates: $generation['rates'] ?? null,
            errorCode: $generation['error']['code'] ?? null,
            errorMessage: $generation['error']['message'] ?? null,
            createdAt: self::date($generation['created_at'] ?? null),
            startedAt: self::date($generation['started_at'] ?? null),
            completedAt: self::date($generation['completed_at'] ?? null),
        );
    }

    public function rates(): ?Rates
    {
        return $this->rates === null ? null : Rates::fromArray($this->rates);
    }

    private static function date(?string $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value)->utc();
    }
}
