<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

function syncGenerationsEvent(): Event
{
    return collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'pixelforge:sync-generations'));
}

it('polls for missed webhooks every minute by default', function () {
    expect(syncGenerationsEvent()->expression)->toBe('*/1 * * * *');
});

it('polls less often when configured, so a scale-to-zero environment can sleep', function () {
    config()->set('services.pixelforge.sync_interval_minutes', 10);

    // The schedule reads the interval when routes/console.php loads, so load it again.
    require base_path('routes/console.php');

    expect(collect(app(Schedule::class)->events())->last()->expression)->toBe('*/10 * * * *');
});
