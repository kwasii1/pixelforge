<?php

namespace App\Enums;

enum GenerationStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }

    /**
     * Whether a generation in this status may move to the given one.
     *
     * Statuses only move forward (queued → processing → succeeded or failed),
     * so a late or repeated event can never undo a later state.
     */
    public function canTransitionTo(self $next): bool
    {
        return ! $this->isFinal() && $next->rank() > $this->rank();
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    private function rank(): int
    {
        return match ($this) {
            self::Queued => 0,
            self::Processing => 1,
            self::Succeeded, self::Failed => 2,
        };
    }
}
