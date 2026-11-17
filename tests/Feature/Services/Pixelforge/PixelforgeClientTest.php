<?php

use App\Enums\GenerationStatus;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeErrorCode;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('creates a generation with the idempotency key and bearer token', function () {
    Http::fake(['pixelforge.test/v1/generations' => Http::response(pixelforgeGeneration(), 202)]);

    $generation = app(PixelforgeClient::class)->createGeneration([
        'prompt' => 'A lighthouse on a cliff at dawn',
        'size' => '1024x1024',
        'quality' => 'medium',
        'reference_images' => [],
        'metadata' => ['generation_id' => 'abc'],
    ], idempotencyKey: 'abc');

    expect($generation->id)->toBe('gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K')
        ->and($generation->status)->toBe(GenerationStatus::Queued);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->hasHeader('Idempotency-Key', 'abc')
        && $request->hasHeader('Authorization', 'Bearer pk_test')
        && $request->data() === [
            'prompt' => 'A lighthouse on a cliff at dawn',
            'size' => '1024x1024',
            'quality' => 'medium',
            'metadata' => ['generation_id' => 'abc'],
        ]);
});

it('sends reference images when there are some', function () {
    Http::fake(['pixelforge.test/v1/generations' => Http::response(pixelforgeGeneration(), 202)]);

    app(PixelforgeClient::class)->createGeneration([
        'prompt' => 'A cat',
        'size' => '1024x1024',
        'quality' => 'low',
        'reference_images' => ['https://example.com/a.png'],
    ], idempotencyKey: 'abc');

    Http::assertSent(fn (Request $request): bool => $request['reference_images'] === ['https://example.com/a.png']);
});

it('parses a succeeded generation', function () {
    Http::fake(['pixelforge.test/v1/generations/gen_1' => Http::response(succeededPixelforgeGeneration(['id' => 'gen_1']))]);

    $generation = app(PixelforgeClient::class)->getGeneration('gen_1');

    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->usage?->reasoning)->toBe(3184)
        ->and($generation->rates()?->reasoningPerMillion)->toBe(12_500_000_000)
        ->and($generation->completedAt?->toIso8601ZuluString())->toBe('2026-11-04T14:03:29Z')
        ->and($generation->imageUrl)->toStartWith('https://pixelforge.promptedit.dev/images/');
});

it('raises a typed, retryable error with the retry delay for limits and overloads', function (int $status, string $code, PixelforgeErrorCode $expected) {
    Http::fake(['pixelforge.test/*' => Http::response(pixelforgeError($code), $status, ['Retry-After' => '5'])]);

    try {
        app(PixelforgeClient::class)->getGeneration('gen_1');
        $this->fail('Expected a PixelforgeException.');
    } catch (PixelforgeException $exception) {
        expect($exception->errorCode)->toBe($expected)
            ->and($exception->isRetryable())->toBeTrue()
            ->and($exception->retryAfter)->toBe(5)
            ->and($exception->status)->toBe($status);
    }
})->with([
    [429, 'rate_limit_exceeded', PixelforgeErrorCode::RateLimitExceeded],
    [429, 'concurrency_limit_exceeded', PixelforgeErrorCode::ConcurrencyLimitExceeded],
    [503, 'overloaded', PixelforgeErrorCode::Overloaded],
]);

it('raises a non-retryable error for rejections that will not change on retry', function (int $status, string $code, PixelforgeErrorCode $expected) {
    Http::fake(['pixelforge.test/*' => Http::response(pixelforgeError($code), $status)]);

    try {
        app(PixelforgeClient::class)->getGeneration('gen_1');
        $this->fail('Expected a PixelforgeException.');
    } catch (PixelforgeException $exception) {
        expect($exception->errorCode)->toBe($expected)
            ->and($exception->isRetryable())->toBeFalse();
    }
})->with([
    [400, 'content_policy_violation', PixelforgeErrorCode::ContentPolicyViolation],
    [429, 'daily_limit_exceeded', PixelforgeErrorCode::DailyLimitExceeded],
    [401, 'invalid_api_key', PixelforgeErrorCode::InvalidApiKey],
    [409, 'idempotency_key_reused', PixelforgeErrorCode::IdempotencyKeyReused],
]);

it('keeps the invalid parameter from validation errors', function () {
    Http::fake(['pixelforge.test/*' => Http::response([
        'error' => [
            'type' => 'invalid_request_error',
            'code' => 'validation_failed',
            'message' => 'The selected size is invalid.',
            'param' => 'size',
            'errors' => ['size' => ['The size must be one of: 1024x1024, 1536x1024, 1024x1536.']],
        ],
    ], 422)]);

    try {
        app(PixelforgeClient::class)->getGeneration('gen_1');
        $this->fail('Expected a PixelforgeException.');
    } catch (PixelforgeException $exception) {
        expect($exception->errorCode)->toBe(PixelforgeErrorCode::ValidationFailed)
            ->and($exception->param)->toBe('size')
            ->and($exception->getMessage())->toBe('The selected size is invalid.');
    }
});

it('treats a connection failure as retryable', function () {
    Http::fake(['pixelforge.test/*' => Http::failedConnection()]);

    try {
        app(PixelforgeClient::class)->getGeneration('gen_1');
        $this->fail('Expected a PixelforgeException.');
    } catch (PixelforgeException $exception) {
        expect($exception->errorCode)->toBe(PixelforgeErrorCode::ConnectionFailed)
            ->and($exception->isRetryable())->toBeTrue();
    }
});

it('treats an undocumented server error as retryable', function () {
    Http::fake(['pixelforge.test/*' => Http::response('Bad gateway', 502)]);

    try {
        app(PixelforgeClient::class)->getGeneration('gen_1');
        $this->fail('Expected a PixelforgeException.');
    } catch (PixelforgeException $exception) {
        expect($exception->errorCode)->toBe(PixelforgeErrorCode::Unexpected)
            ->and($exception->isRetryable())->toBeTrue();
    }
});

it('caches the pricing table', function () {
    Http::fake(['pixelforge.test/v1/pricing' => Http::response(json_decode(
        file_get_contents(base_path('tests/Fixtures/pixelforge/pricing.json')),
        true,
    ))]);

    $client = app(PixelforgeClient::class);
    $client->pricing();
    $pricing = $client->pricing();

    expect($pricing->rates->outputPerMillion)->toBe(40_000_000_000);
    Http::assertSentCount(1);
});

it('falls back to the last known pricing when Pixelforge is unavailable', function () {
    $pricing = json_decode(file_get_contents(base_path('tests/Fixtures/pixelforge/pricing.json')), true);
    Http::fake(['pixelforge.test/v1/pricing' => Http::sequence()
        ->push($pricing)
        ->push(pixelforgeError('overloaded', 'api_error'), 503)]);
    $client = app(PixelforgeClient::class);
    $client->pricing();
    $this->travel(2)->hours();

    $fallback = $client->pricing();

    expect($fallback->rates->outputPerMillion)->toBe(40_000_000_000);
    Http::assertSentCount(2);
});

it('requests usage for an inclusive date range', function () {
    Http::fake(['pixelforge.test/v1/usage*' => Http::response(['object' => 'usage', 'data' => [], 'total' => []])]);

    app(PixelforgeClient::class)->usage('2026-11-01', '2026-11-02');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://pixelforge.test/v1/usage?start_date=2026-11-01&end_date=2026-11-02');
});
