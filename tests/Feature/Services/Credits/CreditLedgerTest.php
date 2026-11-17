<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Services\Credits\CreditLedger;
use App\Services\Generations\GenerationRecorder;
use App\Services\Pixelforge\GenerationData;

/**
 * A processing generation for a user with $1 whose maximum estimate ($0.0863) is held.
 */
function heldGeneration(): Generation
{
    $user = User::factory()->withCredits(1)->create();
    $generation = Generation::factory()->for($user)->processing()->create();
    app(CreditLedger::class)->hold($user, $generation);

    return $generation;
}

it('charges the actual price and returns the rest of the hold when a generation succeeds', function () {
    $generation = heldGeneration();

    app(GenerationRecorder::class)->applyProviderState($generation, GenerationData::fromArray(
        succeededPixelforgeGeneration(['id' => $generation->pixelforge_id]),
    ));

    expect($generation->user->refresh()->balance)->toBe(1_000_000_000 - 381_887_500)
        ->and($generation->creditTransactions()->pluck('amount', 'type')->all())->toEqual([
            'hold' => -86_343_750,
            'release' => 86_343_750,
            'charge' => -381_887_500,
        ]);
});

it('charges once when a success is reported twice', function () {
    $generation = heldGeneration();
    $succeeded = GenerationData::fromArray(succeededPixelforgeGeneration(['id' => $generation->pixelforge_id]));

    app(GenerationRecorder::class)->applyProviderState($generation, $succeeded);
    app(GenerationRecorder::class)->applyProviderState($generation, $succeeded);

    expect($generation->creditTransactions()->where('type', 'charge')->count())->toBe(1)
        ->and($generation->user->refresh()->balance)->toBe(1_000_000_000 - 381_887_500);
});

it('restores the balance exactly when a generation fails', function () {
    $generation = heldGeneration();

    app(GenerationRecorder::class)->applyProviderState($generation, GenerationData::fromArray(pixelforgeGeneration([
        'id' => $generation->pixelforge_id,
        'status' => 'failed',
        'error' => ['code' => 'internal_error', 'message' => 'Oops.'],
    ])));

    expect($generation->refresh()->status)->toBe(GenerationStatus::Failed)
        ->and($generation->user->refresh()->balance)->toBe(1_000_000_000);
});

it('restores the balance when Pixelforge never accepts the generation', function () {
    $generation = heldGeneration();
    $generation->forceFill(['pixelforge_id' => null, 'status' => GenerationStatus::Queued])->save();

    app(GenerationRecorder::class)->markRejected($generation, 'content_policy_violation', 'Rejected.');

    expect($generation->user->refresh()->balance)->toBe(1_000_000_000);
});

it('keeps the balance equal to the sum of the ledger', function () {
    $generation = heldGeneration();
    app(GenerationRecorder::class)->applyProviderState($generation, GenerationData::fromArray(
        succeededPixelforgeGeneration(['id' => $generation->pixelforge_id]),
    ));

    $user = $generation->user->refresh();

    expect($user->balance)->toBe((int) $user->creditTransactions()->sum('amount'));
});

it('refuses to hold more than the balance', function () {
    $user = User::factory()->create();
    $generation = Generation::factory()->for($user)->create();

    expect(app(CreditLedger::class)->hold($user, $generation))->toBeFalse()
        ->and($user->refresh()->balance)->toBe(0)
        ->and($generation->creditTransactions()->count())->toBe(0);
});
