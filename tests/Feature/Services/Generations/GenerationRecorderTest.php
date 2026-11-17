<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Generations\GenerationRecorder;
use App\Services\Pixelforge\GenerationData;

function applyPixelforgeState(Generation $generation, array $pixelforgeGeneration): Generation
{
    return app(GenerationRecorder::class)->applyProviderState($generation, GenerationData::fromArray($pixelforgeGeneration));
}

it('records the exact cost and marked-up price when a generation succeeds', function () {
    $generation = Generation::factory()->processing()->create();

    applyPixelforgeState($generation, succeededPixelforgeGeneration(['id' => $generation->pixelforge_id]));

    expect($generation->refresh())
        ->status->toBe(GenerationStatus::Succeeded)
        ->provider_cost->toBe(305_510_000)
        ->price->toBe(381_887_500)
        ->usage->toEqual([
            'input_text_tokens' => 12,
            'input_image_tokens' => 765,
            'output_tokens' => 6450,
            'reasoning_tokens' => 3184,
            'total_tokens' => 10411,
        ])
        ->completed_at->toIso8601ZuluString()->toBe('2026-11-04T14:03:29Z')
        ->image_url->toStartWith('https://pixelforge.promptedit.dev/images/');
});

it('prices a generation at the rates it was created with', function () {
    $generation = Generation::factory()->processing()->create();

    applyPixelforgeState($generation, succeededPixelforgeGeneration([
        'id' => $generation->pixelforge_id,
        'rates' => [
            'input_text_tokens' => '10.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
    ]));

    expect($generation->refresh()->provider_cost)->toBe(305_570_000);
});

it('records a success only once when it is reported twice', function () {
    $generation = Generation::factory()->processing()->create();
    $succeeded = succeededPixelforgeGeneration(['id' => $generation->pixelforge_id]);

    applyPixelforgeState($generation, $succeeded);
    applyPixelforgeState($generation, array_replace($succeeded, [
        'usage' => ['input_text_tokens' => 99, 'input_image_tokens' => 0, 'output_tokens' => 1, 'reasoning_tokens' => 1, 'total_tokens' => 101],
    ]));

    expect($generation->refresh()->provider_cost)->toBe(305_510_000);
});

it('ignores a processing event that arrives after the generation succeeded', function () {
    $generation = Generation::factory()->succeeded()->create();

    applyPixelforgeState($generation, pixelforgeGeneration(['id' => $generation->pixelforge_id, 'status' => 'processing']));

    expect($generation->refresh())
        ->status->toBe(GenerationStatus::Succeeded)
        ->price->toBe(70_700_000);
});

it('never turns a failed generation into a succeeded one', function () {
    $generation = Generation::factory()->failed()->create();

    applyPixelforgeState($generation, succeededPixelforgeGeneration(['id' => $generation->pixelforge_id]));

    expect($generation->refresh())
        ->status->toBe(GenerationStatus::Failed)
        ->price->toBe(0);
});

it('records a failed generation as free with its error', function () {
    $generation = Generation::factory()->processing()->create();

    applyPixelforgeState($generation, pixelforgeGeneration([
        'id' => $generation->pixelforge_id,
        'status' => 'failed',
        'error' => ['code' => 'internal_error', 'message' => 'Something went wrong on our side.'],
        'completed_at' => '2026-11-04T14:03:29Z',
    ]));

    expect($generation->refresh())
        ->status->toBe(GenerationStatus::Failed)
        ->provider_cost->toBe(0)
        ->price->toBe(0)
        ->error_code->toBe('internal_error')
        ->error_message->toBe('Something went wrong on our side.');
});

it('moves a queued generation to processing and keeps the Pixelforge id', function () {
    $generation = Generation::factory()->create();

    applyPixelforgeState($generation, pixelforgeGeneration([
        'id' => 'gen_from_webhook',
        'status' => 'processing',
        'started_at' => '2026-11-04T14:03:13Z',
    ]));

    expect($generation->refresh())
        ->status->toBe(GenerationStatus::Processing)
        ->pixelforge_id->toBe('gen_from_webhook')
        ->started_at->toIso8601ZuluString()->toBe('2026-11-04T14:03:13Z');
});

it('accepts a success for a generation it failed before learning that Pixelforge accepted it', function () {
    $generation = Generation::factory()->create();
    app(GenerationRecorder::class)->markRejected($generation, 'connection_failed', 'Could not reach Pixelforge.');

    applyPixelforgeState($generation, succeededPixelforgeGeneration(['id' => 'gen_late']));

    expect($generation->refresh())
        ->status->toBe(GenerationStatus::Succeeded)
        ->price->toBe(381_887_500)
        ->error_code->toBeNull();
});

it('refreshes the image URL even when the status does not change', function () {
    $generation = Generation::factory()->succeeded()->create();

    applyPixelforgeState($generation, succeededPixelforgeGeneration([
        'id' => $generation->pixelforge_id,
        'image_url' => 'https://pixelforge.promptedit.dev/images/fresh.png',
    ]));

    expect($generation->refresh()->image_url)->toBe('https://pixelforge.promptedit.dev/images/fresh.png');
});

it('rejects a success without usage so it can be retried', function () {
    $generation = Generation::factory()->processing()->create();

    applyPixelforgeState($generation, pixelforgeGeneration(['id' => $generation->pixelforge_id, 'status' => 'succeeded']));
})->throws(UnexpectedValueException::class);

it('fails a generation Pixelforge never accepted at no cost', function () {
    $generation = Generation::factory()->create();

    app(GenerationRecorder::class)->markRejected($generation, 'content_policy_violation', 'The prompt was rejected.');

    expect($generation->refresh())
        ->status->toBe(GenerationStatus::Failed)
        ->price->toBe(0)
        ->error_code->toBe('content_policy_violation');
});

it('does not fail locally a generation Pixelforge already accepted', function () {
    $generation = Generation::factory()->processing()->create();

    app(GenerationRecorder::class)->markRejected($generation, 'connection_failed', 'Could not reach Pixelforge.');

    expect($generation->refresh()->status)->toBe(GenerationStatus::Processing);
});
