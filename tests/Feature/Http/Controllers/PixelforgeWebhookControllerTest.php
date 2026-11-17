<?php

use App\Enums\GenerationStatus;
use App\Jobs\ProcessPixelforgeEvent;
use App\Models\Generation;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

/**
 * POST a webhook event signed like Pixelforge signs it.
 *
 * @param  array<string, mixed>  $event
 */
function deliverPixelforgeEvent(array $event, ?int $signedAt = null, string $secret = 'whsec_test'): TestResponse
{
    $body = json_encode($event);
    $timestamp = $signedAt ?? now()->getTimestamp();
    $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

    return test()->call('POST', route('webhooks.pixelforge'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        'HTTP_PIXELFORGE_EVENT_ID' => $event['id'],
        'HTTP_PIXELFORGE_DELIVERY_ATTEMPT' => '1',
    ], content: $body);
}

/**
 * @param  array<string, mixed>  $generation
 * @return array<string, mixed>
 */
function pixelforgeEvent(string $type, array $generation, string $id = 'evt_01JC2Z9B7K2M4P6R8T0V3X5Z7A'): array
{
    return [
        'id' => $id,
        'object' => 'event',
        'type' => $type,
        'created_at' => '2026-11-04T14:03:29Z',
        'data' => ['object' => $generation],
    ];
}

it('records a succeeded generation from a signed webhook', function () {
    $generation = Generation::factory()->processing()->create();

    $response = deliverPixelforgeEvent(pixelforgeEvent('generation.succeeded', succeededPixelforgeGeneration(['id' => $generation->pixelforge_id])));

    $response->assertNoContent();
    expect($generation->refresh())
        ->status->toBe(GenerationStatus::Succeeded)
        ->price->toBe(381_887_500);
    expect(WebhookEvent::sole()->processed_at)->not->toBeNull();
});

it('acknowledges a redelivered event without recording it twice', function () {
    Queue::fake();
    $generation = Generation::factory()->processing()->create();
    $event = pixelforgeEvent('generation.succeeded', succeededPixelforgeGeneration(['id' => $generation->pixelforge_id]));

    deliverPixelforgeEvent($event)->assertNoContent();
    $response = deliverPixelforgeEvent($event);

    $response->assertOk();
    expect(WebhookEvent::count())->toBe(1);
    Queue::assertPushed(ProcessPixelforgeEvent::class, 1);
});

it('keeps a generation succeeded when a processing event arrives late', function () {
    $generation = Generation::factory()->processing()->create();

    deliverPixelforgeEvent(pixelforgeEvent('generation.succeeded', succeededPixelforgeGeneration(['id' => $generation->pixelforge_id]), 'evt_2'));
    deliverPixelforgeEvent(pixelforgeEvent('generation.processing', pixelforgeGeneration(['id' => $generation->pixelforge_id, 'status' => 'processing']), 'evt_1'));

    expect($generation->refresh())
        ->status->toBe(GenerationStatus::Succeeded)
        ->price->toBe(381_887_500);
});

it('charges once when the same success arrives under two event ids', function () {
    $generation = Generation::factory()->processing()->create();
    $succeeded = succeededPixelforgeGeneration(['id' => $generation->pixelforge_id]);

    deliverPixelforgeEvent(pixelforgeEvent('generation.succeeded', $succeeded, 'evt_a'));
    deliverPixelforgeEvent(pixelforgeEvent('generation.succeeded', array_replace($succeeded, [
        'usage' => ['input_text_tokens' => 1, 'input_image_tokens' => 0, 'output_tokens' => 1, 'reasoning_tokens' => 1, 'total_tokens' => 3],
    ]), 'evt_b'));

    expect($generation->refresh()->price)->toBe(381_887_500);
});

it('matches a generation by our id when the webhook beats the create response', function () {
    $generation = Generation::factory()->create();

    deliverPixelforgeEvent(pixelforgeEvent('generation.succeeded', succeededPixelforgeGeneration([
        'id' => 'gen_not_yet_saved',
        'metadata' => ['generation_id' => $generation->id],
    ])));

    expect($generation->refresh())
        ->pixelforge_id->toBe('gen_not_yet_saved')
        ->status->toBe(GenerationStatus::Succeeded);
});

it('records a failed generation as free', function () {
    $generation = Generation::factory()->processing()->create();

    deliverPixelforgeEvent(pixelforgeEvent('generation.failed', pixelforgeGeneration([
        'id' => $generation->pixelforge_id,
        'status' => 'failed',
        'error' => ['code' => 'generation_failed', 'message' => 'The model could not produce an image.'],
    ])));

    expect($generation->refresh())
        ->status->toBe(GenerationStatus::Failed)
        ->price->toBe(0);
});

it('acknowledges events for generations it has no record of', function () {
    $response = deliverPixelforgeEvent(pixelforgeEvent('generation.succeeded', succeededPixelforgeGeneration(['id' => 'gen_unknown'])));

    $response->assertNoContent();
    expect(WebhookEvent::sole()->processed_at)->not->toBeNull();
});

it('acknowledges a test ping', function () {
    deliverPixelforgeEvent(['id' => 'evt_ping', 'object' => 'event', 'type' => 'ping', 'data' => ['object' => []]])
        ->assertNoContent();
});

it('rejects a request with a bad signature with 400', function () {
    $generation = Generation::factory()->processing()->create();

    $response = deliverPixelforgeEvent(
        pixelforgeEvent('generation.succeeded', succeededPixelforgeGeneration(['id' => $generation->pixelforge_id])),
        secret: 'whsec_wrong',
    );

    $response->assertBadRequest();
    expect(WebhookEvent::count())->toBe(0)
        ->and($generation->refresh()->status)->toBe(GenerationStatus::Processing);
});

it('rejects a request without a signature with 400', function () {
    $this->postJson(route('webhooks.pixelforge'), pixelforgeEvent('ping', []))->assertBadRequest();
});

it('rejects a replayed request signed more than five minutes ago with 400', function () {
    $response = deliverPixelforgeEvent(
        pixelforgeEvent('generation.succeeded', succeededPixelforgeGeneration()),
        signedAt: now()->subMinutes(6)->getTimestamp(),
    );

    $response->assertBadRequest();
});

it('rejects a body that was changed after signing with 400', function () {
    $timestamp = now()->getTimestamp();
    $signature = hash_hmac('sha256', $timestamp.'.{"id":"evt_1","type":"ping"}', 'whsec_test');

    $response = $this->call('POST', route('webhooks.pixelforge'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ], content: '{"id":"evt_2","type":"ping"}');

    $response->assertBadRequest();
});
