<?php

namespace Database\Factories;

use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventId = 'evt_'.Str::upper(Str::random(26));

        return [
            'event_id' => $eventId,
            'type' => 'ping',
            'payload' => ['id' => $eventId, 'object' => 'event', 'type' => 'ping', 'data' => ['object' => []]],
        ];
    }
}
