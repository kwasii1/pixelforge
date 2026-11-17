<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\WebhookEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A webhook event received from Pixelforge, stored once per event id.
 *
 * @property string $id
 * @property string $event_id
 * @property string $type
 * @property string|null $pixelforge_generation_id
 * @property array<string, mixed> $payload
 * @property int $delivery_attempt
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['event_id', 'type', 'pixelforge_generation_id', 'payload', 'delivery_attempt'])]
class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory, HasUuids;

    /**
     * The generation object carried by a generation.* event.
     *
     * @return array<string, mixed>|null
     */
    public function generationPayload(): ?array
    {
        $object = $this->payload['data']['object'] ?? null;

        return is_array($object) && ($object['object'] ?? null) === 'generation' ? $object : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'delivery_attempt' => 'integer',
            'processed_at' => 'datetime',
        ];
    }
}
