<?php

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function submitGeneration(Generation $generation): SubmitGeneration
{
    $job = (new SubmitGeneration($generation))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    return $job;
}

it('submits the generation with its id as the idempotency key and records the Pixelforge id', function () {
    $generation = Generation::factory()->create([
        'prompt' => 'A red fox',
        'reference_images' => ['https://example.com/a.png'],
    ]);
    Http::fake(['pixelforge.test/v1/generations' => Http::response(pixelforgeGeneration(['id' => 'gen_new']), 202)]);

    submitGeneration($generation);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Idempotency-Key', $generation->id)
        && $request['prompt'] === 'A red fox'
        && $request['size'] === '1024x1024'
        && $request['quality'] === 'medium'
        && $request['reference_images'] === ['https://example.com/a.png']
        && $request['metadata'] === ['generation_id' => $generation->id]);
    expect($generation->refresh())
        ->pixelforge_id->toBe('gen_new')
        ->status->toBe(GenerationStatus::Queued)
        ->submission_attempts->toBe(1)
        ->submitted_at->not->toBeNull();
});

it('waits for the Retry-After delay when the concurrency limit is reached', function () {
    $generation = Generation::factory()->create();
    Http::fake(['pixelforge.test/*' => Http::response(pixelforgeError('concurrency_limit_exceeded'), 429, ['Retry-After' => '5'])]);

    $job = submitGeneration($generation);

    $job->assertReleased(delay: 5);
    expect($generation->refresh())
        ->status->toBe(GenerationStatus::Queued)
        ->isWaitingForCapacity()->toBeTrue();
});

it('backs off further on each attempt while Pixelforge is overloaded', function () {
    $generation = Generation::factory()->create(['submission_attempts' => 3]);
    Http::fake(['pixelforge.test/*' => Http::response(pixelforgeError('overloaded', 'api_error'), 503, ['Retry-After' => '2'])]);

    $job = submitGeneration($generation);

    $job->assertReleased(delay: 16);
});

it('retries after a connection failure', function () {
    $generation = Generation::factory()->create();
    Http::fake(['pixelforge.test/*' => Http::failedConnection()]);

    $job = submitGeneration($generation);

    $job->assertReleased(delay: 2);
    expect($generation->refresh()->status)->toBe(GenerationStatus::Queued);
});

it('reuses the same idempotency key when it retries', function () {
    $generation = Generation::factory()->create();
    Http::fake(['pixelforge.test/v1/generations' => Http::sequence()
        ->push(pixelforgeError('overloaded', 'api_error'), 503, ['Retry-After' => '2'])
        ->push(pixelforgeGeneration(['id' => 'gen_new']), 202)]);

    submitGeneration($generation);
    submitGeneration($generation);

    $keys = Http::recorded()->map(fn (array $pair): string => $pair[0]->header('Idempotency-Key')[0]);
    expect($keys->all())->toBe([$generation->id, $generation->id])
        ->and($generation->refresh()->pixelforge_id)->toBe('gen_new');
});

it('fails a prompt rejected by the content policy at no cost', function () {
    $generation = Generation::factory()->create();
    Http::fake(['pixelforge.test/*' => Http::response(pixelforgeError('content_policy_violation', 'invalid_request_error', 'The prompt was rejected.'), 400)]);

    $job = submitGeneration($generation);

    $job->assertNotReleased();
    expect($generation->refresh())
        ->status->toBe(GenerationStatus::Failed)
        ->price->toBe(0)
        ->error_code->toBe('content_policy_violation');
});

it('fails immediately when the daily limit is used up', function () {
    $generation = Generation::factory()->create();
    Http::fake(['pixelforge.test/*' => Http::response(pixelforgeError('daily_limit_exceeded'), 429)]);

    $job = submitGeneration($generation);

    $job->assertNotReleased();
    expect($generation->refresh()->failureReason())->toContain('daily generation limit');
});

it('records the state Pixelforge returns on an idempotent replay', function () {
    $generation = Generation::factory()->create();
    Http::fake(['pixelforge.test/v1/generations' => Http::response(
        succeededPixelforgeGeneration(['id' => 'gen_replayed']),
        202,
        ['Idempotent-Replayed' => 'true'],
    )]);

    submitGeneration($generation);

    expect($generation->refresh())
        ->status->toBe(GenerationStatus::Succeeded)
        ->price->toBe(381_887_500);
});

it('does nothing for a generation Pixelforge already accepted', function () {
    $generation = Generation::factory()->processing()->create();

    submitGeneration($generation);

    Http::assertNothingSent();
});

it('fails the generation for free when Pixelforge stays busy for the whole window', function () {
    $generation = Generation::factory()->create(['submission_attempts' => 12]);

    (new SubmitGeneration($generation))->failed(null);

    expect($generation->refresh())
        ->status->toBe(GenerationStatus::Failed)
        ->price->toBe(0)
        ->error_code->toBe('submission_timed_out');
});
