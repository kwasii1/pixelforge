<?php

namespace App\Services\Pixelforge;

enum PixelforgeErrorCode: string
{
    case InvalidApiKey = 'invalid_api_key';
    case ValidationFailed = 'validation_failed';
    case ContentPolicyViolation = 'content_policy_violation';
    case NotFound = 'not_found';
    case IdempotencyKeyReused = 'idempotency_key_reused';
    case RateLimitExceeded = 'rate_limit_exceeded';
    case ConcurrencyLimitExceeded = 'concurrency_limit_exceeded';
    case DailyLimitExceeded = 'daily_limit_exceeded';
    case Overloaded = 'overloaded';
    case DateRangeTooLong = 'date_range_too_long';

    /** The request never got a response (timeout, DNS, refused connection). Not a Pixelforge code. */
    case ConnectionFailed = 'connection_failed';

    /** A response Pixelforge's docs don't describe, such as a 5xx without an error body. Not a Pixelforge code. */
    case Unexpected = 'unexpected';

    /**
     * Whether trying the same request again later can succeed.
     */
    public function isRetryable(): bool
    {
        return match ($this) {
            self::RateLimitExceeded,
            self::ConcurrencyLimitExceeded,
            self::Overloaded,
            self::ConnectionFailed,
            self::Unexpected => true,
            default => false,
        };
    }
}
