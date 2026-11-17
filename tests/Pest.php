<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A Pixelforge generation object as the API returns it, queued by default.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pixelforgeGeneration(array $overrides = []): array
{
    return array_replace([
        'id' => 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K',
        'object' => 'generation',
        'status' => 'queued',
        'prompt' => 'A lighthouse on a cliff at dawn',
        'size' => '1024x1024',
        'quality' => 'medium',
        'reference_images' => [],
        'metadata' => [],
        'image_url' => null,
        'image_url_expires_at' => null,
        'usage' => null,
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
        'error' => null,
        'created_at' => '2026-11-04T14:03:11Z',
        'started_at' => null,
        'completed_at' => null,
    ], $overrides);
}

/**
 * A succeeded Pixelforge generation with the usage from the docs ($0.305510000 provider cost).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function succeededPixelforgeGeneration(array $overrides = []): array
{
    return pixelforgeGeneration(array_replace([
        'status' => 'succeeded',
        'image_url' => 'https://pixelforge.promptedit.dev/images/gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K.png?expires=1762351409&signature=abc',
        'image_url_expires_at' => '2026-11-05T14:03:29Z',
        'usage' => [
            'input_text_tokens' => 12,
            'input_image_tokens' => 765,
            'output_tokens' => 6450,
            'reasoning_tokens' => 3184,
            'total_tokens' => 10411,
        ],
        'started_at' => '2026-11-04T14:03:13Z',
        'completed_at' => '2026-11-04T14:03:29Z',
    ], $overrides));
}

/**
 * The JSON error body Pixelforge returns.
 *
 * @return array{error: array{type: string, code: string, message: string}}
 */
function pixelforgeError(string $code, string $type = 'rate_limit_error', string $message = 'Pixelforge error.'): array
{
    return ['error' => ['type' => $type, 'code' => $code, 'message' => $message]];
}
