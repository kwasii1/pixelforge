<?php

namespace App\Actions\Generations;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Services\Credits\CreditLedger;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pricing\PricingCalculator;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a user's generation request with its estimate, holds the most it can
 * cost from their credits, then hands it to the queue for submission.
 *
 * The balance check and the hold happen under a lock on the user row, so two
 * submissions at once can't both spend the same credits.
 */
class CreateGeneration
{
    public function __construct(
        private readonly PixelforgeClient $pixelforge,
        private readonly PricingCalculator $calculator,
        private readonly CreditLedger $ledger,
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

        return DB::transaction(function () use ($user, $prompt, $size, $quality, $referenceImages, $estimate): Generation {
            // Lock the user before inserting: the generation's foreign key would otherwise take a
            // shared lock first, and two submissions upgrading it at once would deadlock.
            $this->ledger->lockAccount($user);

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

            if (! $this->ledger->hold($user, $generation)) {
                throw ValidationException::withMessages([
                    'credits' => sprintf(
                        'Not enough credits. This could cost up to %s and your balance is %s.',
                        Money::format($estimate->maximumPrice),
                        Money::format($user->balance),
                    ),
                ]);
            }

            SubmitGeneration::dispatch($generation)->afterCommit();

            return $generation;
        });
    }
}
