<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Models\WebhookEvent;
use App\Services\Generations\GenerationRecorder;
use App\Services\Pixelforge\GenerationData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Applies a stored Pixelforge event to its generation.
 *
 * Events can arrive in any order, so this never trusts the event type: it hands
 * the generation object inside the event to GenerationRecorder, which only moves
 * a generation forward and prices it once.
 */
class ProcessPixelforgeEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 30, 120, 600];

    public function __construct(public WebhookEvent $event) {}

    public function handle(GenerationRecorder $recorder): void
    {
        if ($this->event->processed_at !== null) {
            return;
        }

        $object = $this->event->generationPayload();

        if ($object !== null) {
            $data = GenerationData::fromArray($object);
            $generation = $this->findGeneration($data);

            if ($generation === null) {
                Log::info('Ignoring Pixelforge event for a generation we have no record of.', [
                    'event_id' => $this->event->event_id,
                    'pixelforge_generation_id' => $data->id,
                ]);
            } else {
                $recorder->applyProviderState($generation, $data);
            }
        }

        $this->event->forceFill(['processed_at' => now()])->save();
    }

    /**
     * By Pixelforge id, or by our own id in the metadata when the event beat the create response.
     */
    private function findGeneration(GenerationData $data): ?Generation
    {
        $generationId = $data->metadata['generation_id'] ?? null;

        return Generation::query()
            ->where('pixelforge_id', $data->id)
            ->when(is_string($generationId), fn ($query) => $query->orWhere('id', $generationId))
            ->first();
    }
}
