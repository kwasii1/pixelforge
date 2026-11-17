<?php

namespace App\Console\Commands;

use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Services\Generations\GenerationRecorder;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The safety net for webhooks, which Pixelforge doesn't guarantee to deliver.
 *
 * Polls Pixelforge for generations that haven't changed for a while, and
 * re-queues generations whose submission job was lost. Everything goes through
 * GenerationRecorder, so it's safe to run alongside webhooks.
 */
#[Signature('pixelforge:sync-generations {--limit=20 : The most generations to poll per run}')]
#[Description('Poll Pixelforge for generations whose webhooks are late or missing')]
class SyncPixelforgeGenerations extends Command
{
    /**
     * How long a generation can go without news before we ask Pixelforge directly.
     */
    private const QUIET_MINUTES = 2;

    public function handle(PixelforgeClient $pixelforge, GenerationRecorder $recorder): int
    {
        $this->pollStaleGenerations($pixelforge, $recorder);
        $this->resubmitStrandedGenerations($recorder);

        return self::SUCCESS;
    }

    private function pollStaleGenerations(PixelforgeClient $pixelforge, GenerationRecorder $recorder): void
    {
        $stale = Generation::query()
            ->inProgress()
            ->whereNotNull('pixelforge_id')
            ->where('updated_at', '<', now()->subMinutes(self::QUIET_MINUTES))
            ->oldest('updated_at')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($stale as $generation) {
            try {
                $recorder->applyProviderState($generation, $pixelforge->getGeneration((string) $generation->pixelforge_id));
                // Touch so an unchanged generation goes to the back of the queue for the next run.
                $generation->touch();
            } catch (PixelforgeException $exception) {
                $this->components->warn("Could not poll generation [{$generation->id}]: {$exception->getMessage()}");

                if ($exception->isRetryable()) {
                    // Rate limited or overloaded: leave the rest for the next run.
                    return;
                }
            }
        }
    }

    /**
     * Generations Pixelforge never acknowledged: re-queue them, or fail them for free once the submission window has passed.
     */
    private function resubmitStrandedGenerations(GenerationRecorder $recorder): void
    {
        $stranded = Generation::query()
            ->inProgress()
            ->whereNull('pixelforge_id')
            ->where('updated_at', '<', now()->subMinutes(self::QUIET_MINUTES))
            ->get();

        foreach ($stranded as $generation) {
            if ($generation->created_at?->addMinutes(SubmitGeneration::SUBMISSION_WINDOW_MINUTES + self::QUIET_MINUTES)->isPast()) {
                $recorder->markRejected($generation, 'submission_timed_out', 'Pixelforge stayed busy for too long, so the generation was never started.');

                continue;
            }

            // Unique per generation, so this is a no-op while its job is still waiting in the queue.
            SubmitGeneration::dispatch($generation);
        }
    }
}
