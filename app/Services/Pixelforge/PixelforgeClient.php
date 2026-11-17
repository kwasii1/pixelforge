<?php

namespace App\Services\Pixelforge;

use App\Services\Pricing\PricingTable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * A thin client for the Pixelforge API. Every failure surfaces as a PixelforgeException.
 *
 * It never retries on its own: callers decide, because only they know whether
 * waiting is acceptable (a queued job can wait, a web request should not).
 */
class PixelforgeClient
{
    private const PRICING_CACHE_KEY = 'pixelforge.pricing';

    private const PRICING_CACHE_SECONDS = 3600;

    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
    ) {}

    /**
     * Create a generation. Retrying with the same idempotency key and parameters never creates a second one.
     *
     * @param  array{prompt: string, size: string, quality: string, reference_images?: list<string>, metadata?: array<string, string>}  $parameters
     */
    public function createGeneration(array $parameters, string $idempotencyKey): GenerationData
    {
        if (empty($parameters['reference_images'])) {
            unset($parameters['reference_images']);
        }

        $response = $this->send(fn (PendingRequest $request): Response => $request
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('generations', $parameters));

        return GenerationData::fromArray($response->json());
    }

    public function getGeneration(string $id): GenerationData
    {
        $response = $this->send(fn (PendingRequest $request): Response => $request->get('generations/'.rawurlencode($id)));

        return GenerationData::fromArray($response->json());
    }

    /**
     * The current rates and token tables. Public and slow to change, so cached for an hour.
     */
    public function pricing(): PricingTable
    {
        $pricing = Cache::remember(
            self::PRICING_CACHE_KEY,
            self::PRICING_CACHE_SECONDS,
            fn (): array => $this->send(fn (PendingRequest $request): Response => $request->get('pricing'))->json(),
        );

        return PricingTable::fromArray($pricing);
    }

    /**
     * What Pixelforge billed this API key per UTC day (inclusive range, at most 92 days).
     *
     * @return array{data: list<array{date: string, generations: int, input_text_tokens: int, input_image_tokens: int, output_tokens: int, reasoning_tokens: int, cost_usd: string}>, total: array<string, int|string>}
     */
    public function usage(string $startDate, string $endDate): array
    {
        return $this->send(fn (PendingRequest $request): Response => $request->get('usage', [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]))->json();
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call): Response
    {
        try {
            $response = $call($this->request());
        } catch (ConnectionException $exception) {
            throw PixelforgeException::connectionFailed($exception);
        }

        if ($response->failed()) {
            throw PixelforgeException::fromResponse($response);
        }

        return $response;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken((string) $this->apiKey)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15);
    }
}
