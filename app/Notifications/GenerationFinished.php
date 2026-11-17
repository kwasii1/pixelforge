<?php

namespace App\Notifications;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Support\Money;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells a user their generation succeeded (and what it cost) or failed (and why, at no cost).
 *
 * Stored in the database for the header bell; the browser also shows new ones as toasts.
 */
class GenerationFinished extends Notification
{
    public function __construct(public Generation $generation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'generation.finished';
    }

    /**
     * @return array{generation_id: string, status: string, title: string, prompt: string, detail: string}
     */
    public function toArray(object $notifiable): array
    {
        $succeeded = $this->generation->status === GenerationStatus::Succeeded;

        return [
            'generation_id' => $this->generation->id,
            'status' => $this->generation->status->value,
            'title' => $succeeded ? 'Image ready' : 'Generation failed',
            'prompt' => Str::limit($this->generation->prompt, 80),
            'detail' => $succeeded
                ? 'Charged '.Money::format((int) $this->generation->price)
                : 'Not charged. '.$this->generation->failureReason(),
        ];
    }
}
