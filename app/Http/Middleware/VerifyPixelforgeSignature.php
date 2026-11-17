<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects webhook requests that weren't signed by Pixelforge, or were signed too long ago.
 *
 * The Pixelforge-Signature header is "t=<unix time>,v1=<hex HMAC-SHA256 of "<t>.<raw body>">",
 * keyed with the webhook signing secret of the API key that created the generation.
 */
class VerifyPixelforgeSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.pixelforge.webhook_secret');
        $signature = $this->parseSignatureHeader((string) $request->header('Pixelforge-Signature'));
        $timestamp = (int) ($signature['t'] ?? 0);

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        abort_if(
            $secret === ''
                || ! hash_equals($expected, $signature['v1'] ?? '')
                || abs(now()->getTimestamp() - $timestamp) > (int) config('services.pixelforge.webhook_tolerance_seconds'),
            400,
            'Invalid Pixelforge signature.',
        );

        return $next($request);
    }

    /**
     * @return array<string, string>
     */
    private function parseSignatureHeader(string $header): array
    {
        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key] = $value;
        }

        return $parts;
    }
}
