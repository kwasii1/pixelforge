<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPixelforgeEvent;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Receives Pixelforge webhooks. The signature is checked by VerifyPixelforgeSignature.
 *
 * It only stores the event and queues it, so Pixelforge gets its 2xx well within
 * its 10 second limit. Event ids are unique in the database, so an event that is
 * delivered again is acknowledged without being stored or processed a second time.
 */
class PixelforgeWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $payload = $request->json()->all();

        abort_unless(is_string($payload['id'] ?? null) && is_string($payload['type'] ?? null), 400, 'Malformed Pixelforge event.');

        try {
            DB::transaction(function () use ($payload, $request): void {
                $event = WebhookEvent::create([
                    'event_id' => $payload['id'],
                    'type' => $payload['type'],
                    'pixelforge_generation_id' => $payload['data']['object']['id'] ?? null,
                    'payload' => $payload,
                    'delivery_attempt' => max(1, (int) $request->header('Pixelforge-Delivery-Attempt', '1')),
                ]);

                ProcessPixelforgeEvent::dispatch($event);
            });
        } catch (UniqueConstraintViolationException) {
            return response('Already received.', 200);
        }

        return response()->noContent();
    }
}
