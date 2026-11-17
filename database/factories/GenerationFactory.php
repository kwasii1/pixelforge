<?php

namespace Database\Factories;

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Amounts mirror a medium 1024x1024 prompt at the published rates.
 *
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'prompt' => fake()->sentence(),
            'size' => ImageSize::Square,
            'quality' => ImageQuality::Medium,
            'reference_images' => [],
            'status' => GenerationStatus::Queued,
            'estimated_min_cost' => 46_525_000,
            'estimated_max_cost' => 69_075_000,
            'estimated_min_price' => 58_156_250,
            'estimated_max_price' => 86_343_750,
        ];
    }

    /**
     * Accepted by Pixelforge and waiting in its queue.
     */
    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'pixelforge_id' => 'gen_'.Str::upper(Str::random(26)),
            'submitted_at' => now(),
        ]);
    }

    public function processing(): static
    {
        return $this->submitted()->state(fn (): array => [
            'status' => GenerationStatus::Processing,
            'started_at' => now(),
        ]);
    }

    public function succeeded(): static
    {
        return $this->processing()->state(fn (): array => [
            'status' => GenerationStatus::Succeeded,
            'usage' => [
                'input_text_tokens' => 12,
                'input_image_tokens' => 0,
                'output_tokens' => 1100,
                'reasoning_tokens' => 1000,
                'total_tokens' => 2112,
            ],
            'rates' => [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ],
            'provider_cost' => 56_560_000,
            'price' => 70_700_000,
            'image_url' => 'https://pixelforge.promptedit.dev/images/example.png',
            'image_url_expires_at' => now()->addDay(),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->processing()->state(fn (): array => [
            'status' => GenerationStatus::Failed,
            'provider_cost' => 0,
            'price' => 0,
            'error_code' => 'generation_failed',
            'error_message' => 'The model could not produce an image for this prompt.',
            'completed_at' => now(),
        ]);
    }
}
