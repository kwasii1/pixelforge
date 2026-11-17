<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Services\Generations\GenerationRecorder;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeErrorCode;
use App\Services\Pixelforge\PixelforgeException;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends a generation to Pixelforge, waiting out rate limits, concurrency limits and overloads.
 *
 * The generation's UUID is the Idempotency-Key, so however many times this
 * runs (retries, a worker crash, a duplicate dispatch), Pixelforge creates
 * and bills at most one generation for it.
 */
class SubmitGeneration implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * How long to keep trying while Pixelforge is busy before giving up.
     */
    public const SUBMISSION_WINDOW_MINUTES = 10;

    /**
     * Seconds to wait before attempt n+1 when Pixelforge doesn't say how long, capped at the last value.
     */
    private const BACKOFF_SECONDS = [2, 4, 8, 16, 30, 60];

    public int $uniqueFor = self::SUBMISSION_WINDOW_MINUTES * 60;

    public function __construct(public Generation $generation) {}

    public function uniqueId(): string
    {
        return $this->generation->id;
    }

    public function retryUntil(): DateTimeInterface
    {
        return $this->generation->created_at?->addMinutes(self::SUBMISSION_WINDOW_MINUTES)
            ?? now()->addMinutes(self::SUBMISSION_WINDOW_MINUTES);
    }

    public function handle(PixelforgeClient $pixelforge, GenerationRecorder $recorder): void
    {
        $generation = $this->generation->refresh();

        if ($generation->status->isFinal() || $generation->isKnownToPixelforge()) {
            return;
        }

        $generation->increment('submission_attempts');

        try {
            $accepted = $pixelforge->createGeneration([
                'prompt' => $generation->prompt,
                'size' => $generation->size->value,
                'quality' => $generation->quality->value,
                'reference_images' => $generation->reference_images,
                'metadata' => ['generation_id' => $generation->id],
            ], idempotencyKey: $generation->id);
        } catch (PixelforgeException $exception) {
            if ($exception->isRetryable()) {
                $this->release($this->delayBeforeRetry($exception, $generation->submission_attempts));

                return;
            }

            $recorder->markRejected($generation, $exception->errorCode->value, $exception->getMessage());

            return;
        }

        $recorder->applyProviderState($generation, $accepted);
    }

    /**
     * Pixelforge never accepted it within the window, so nothing was created or billed.
     */
    public function failed(?Throwable $exception): void
    {
        app(GenerationRecorder::class)->markRejected(
            $this->generation,
            'submission_timed_out',
            'Pixelforge stayed busy for too long, so the generation was never started.',
        );
    }

    /**
     * Honour Retry-After, back off further on each attempt, and never wait more than a minute.
     */
    private function delayBeforeRetry(PixelforgeException $exception, int $attempts): int
    {
        $backoff = self::BACKOFF_SECONDS[min($attempts, count(self::BACKOFF_SECONDS)) - 1];

        if ($exception->errorCode === PixelforgeErrorCode::ConcurrencyLimitExceeded) {
            // A slot frees up as soon as any running generation finishes, so poll at Pixelforge's pace.
            return $exception->retryAfter ?? $backoff;
        }

        return min(max($exception->retryAfter ?? 0, $backoff), 60);
    }
}
