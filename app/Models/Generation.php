<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use Carbon\CarbonImmutable;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An image generation a user asked for. Money columns are nanodollars.
 *
 * @property string $id
 * @property int $user_id
 * @property string|null $pixelforge_id
 * @property string $prompt
 * @property ImageSize $size
 * @property ImageQuality $quality
 * @property list<string> $reference_images
 * @property GenerationStatus $status
 * @property int $submission_attempts
 * @property int $estimated_min_cost
 * @property int $estimated_max_cost
 * @property int $estimated_min_price
 * @property int $estimated_max_price
 * @property int|null $provider_cost
 * @property int|null $price
 * @property array<string, int>|null $usage
 * @property array<string, string>|null $rates
 * @property string|null $image_url
 * @property CarbonImmutable|null $image_url_expires_at
 * @property string|null $error_code
 * @property string|null $error_message
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'prompt',
    'size',
    'quality',
    'reference_images',
    'estimated_min_cost',
    'estimated_max_cost',
    'estimated_min_price',
    'estimated_max_price',
])]
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'queued',
        'submission_attempts' => 0,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generations that aren't finished yet.
     *
     * @param  Builder<self>  $query
     */
    public function scopeInProgress(Builder $query): void
    {
        $query->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing]);
    }

    /**
     * Still waiting on our side for Pixelforge to accept it, after at least one busy response.
     */
    public function isWaitingForCapacity(): bool
    {
        return $this->status === GenerationStatus::Queued
            && $this->submitted_at === null
            && $this->submission_attempts > 0;
    }

    /**
     * Pixelforge accepted this generation, so it may be billed.
     */
    public function isKnownToPixelforge(): bool
    {
        return $this->pixelforge_id !== null;
    }

    /**
     * Why a failed generation failed, in words a user can act on.
     */
    public function failureReason(): ?string
    {
        if ($this->status !== GenerationStatus::Failed) {
            return null;
        }

        return match ($this->error_code) {
            'generation_failed' => "Pixelforge couldn't produce an image for this prompt. Try rewording it.",
            'internal_error' => 'Pixelforge had an internal error. Trying again usually works.',
            'content_policy_violation' => 'Pixelforge rejected this prompt under its content policy.',
            'daily_limit_exceeded' => 'The daily generation limit is used up. It resets at 00:00 UTC.',
            'submission_timed_out' => 'Pixelforge stayed busy for too long, so this was never started.',
            default => $this->error_message ?? 'Something went wrong.',
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => ImageSize::class,
            'quality' => ImageQuality::class,
            'status' => GenerationStatus::class,
            'reference_images' => 'array',
            'usage' => 'array',
            'rates' => 'array',
            'estimated_min_cost' => 'integer',
            'estimated_max_cost' => 'integer',
            'estimated_min_price' => 'integer',
            'estimated_max_price' => 'integer',
            'provider_cost' => 'integer',
            'price' => 'integer',
            'image_url_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
