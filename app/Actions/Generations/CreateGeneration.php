<?php

namespace App\Actions\Generations;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pricing\PricingCalculator;

/**
 * Records a user's generation request with its estimate, then hands it to the queue for submission.
 */
class CreateGeneration
{
    public function __construct(
        private readonly PixelforgeClient $pixelforge,
        private readonly PricingCalculator $calculator,
    ) {}

    /**
     * @param  list<string>  $referenceImages
     */
    public function handle(User $user, string $prompt, ImageSize $size, ImageQuality $quality, array $referenceImages): Generation
    {
        $estimate = $this->calculator->estimate(
            $this->pixelforge->pricing(),
            $prompt,
            $size,
            $quality,
            count($referenceImages),
        );

        $generation = $user->generations()->create([
            'prompt' => $prompt,
            'size' => $size,
            'quality' => $quality,
            'reference_images' => $referenceImages,
            'estimated_min_cost' => $estimate->minimumCost,
            'estimated_max_cost' => $estimate->maximumCost,
            'estimated_min_price' => $estimate->minimumPrice,
            'estimated_max_price' => $estimate->maximumPrice,
        ]);

        SubmitGeneration::dispatch($generation)->afterCommit();

        return $generation;
    }
}
