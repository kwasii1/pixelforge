<?php

namespace App\Services\Pixelforge;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

class PixelforgeException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        public readonly PixelforgeErrorCode $errorCode,
        string $message,
        public readonly ?int $status = null,
        public readonly ?int $retryAfter = null,
        public readonly ?string $param = null,
        public readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromResponse(Response $response): self
    {
        $error = (array) $response->json('error', []);

        $retryAfter = $response->header('Retry-After');

        return new self(
            errorCode: PixelforgeErrorCode::tryFrom((string) ($error['code'] ?? '')) ?? PixelforgeErrorCode::Unexpected,
            message: (string) ($error['message'] ?? "Pixelforge responded with HTTP {$response->status()}."),
            status: $response->status(),
            retryAfter: is_numeric($retryAfter) ? (int) $retryAfter : null,
            param: isset($error['param']) ? (string) $error['param'] : null,
            errors: (array) ($error['errors'] ?? []),
        );
    }

    public static function connectionFailed(Throwable $previous): self
    {
        return new self(
            errorCode: PixelforgeErrorCode::ConnectionFailed,
            message: 'Could not reach Pixelforge: '.$previous->getMessage(),
            previous: $previous,
        );
    }

    public function isRetryable(): bool
    {
        return $this->errorCode->isRetryable();
    }
}
