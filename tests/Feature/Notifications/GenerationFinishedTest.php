<?php

use App\Models\Generation;
use App\Models\User;
use App\Services\Generations\GenerationRecorder;
use App\Services\Pixelforge\GenerationData;
use Inertia\Testing\AssertableInertia as Assert;

function reportToRecorder(Generation $generation, array $pixelforgeGeneration): void
{
    app(GenerationRecorder::class)->applyProviderState($generation, GenerationData::fromArray($pixelforgeGeneration));
}

it('notifies the user once when a generation succeeds, with what it cost', function () {
    $generation = Generation::factory()->processing()->create(['prompt' => 'A red fox']);
    $succeeded = succeededPixelforgeGeneration(['id' => $generation->pixelforge_id]);

    reportToRecorder($generation, $succeeded);
    reportToRecorder($generation, $succeeded);

    $notification = $generation->user->notifications()->sole();
    expect($notification->type)->toBe('generation.finished')
        ->and($notification->data)->toMatchArray([
            'generation_id' => $generation->id,
            'status' => 'succeeded',
            'title' => 'Image ready',
            'prompt' => 'A red fox',
            'detail' => 'Charged $0.3819',
        ]);
});

it('notifies the user that a failed generation was not charged', function () {
    $generation = Generation::factory()->processing()->create();

    reportToRecorder($generation, pixelforgeGeneration([
        'id' => $generation->pixelforge_id,
        'status' => 'failed',
        'error' => ['code' => 'internal_error', 'message' => 'Oops.'],
    ]));

    expect($generation->user->notifications()->sole()->data)->toMatchArray([
        'status' => 'failed',
        'title' => 'Generation failed',
        'detail' => 'Not charged. Pixelforge had an internal error. Trying again usually works.',
    ]);
});

it('notifies the user when Pixelforge rejects a generation at submission', function () {
    $generation = Generation::factory()->create();

    app(GenerationRecorder::class)->markRejected($generation, 'content_policy_violation', 'Rejected.');

    expect($generation->user->notifications()->sole()->data['title'])->toBe('Generation failed');
});

it('does not notify for a status change that is not final', function () {
    $generation = Generation::factory()->submitted()->create();

    reportToRecorder($generation, pixelforgeGeneration(['id' => $generation->pixelforge_id, 'status' => 'processing']));

    expect($generation->user->notifications()->count())->toBe(0);
});

it('shares unread notifications and whether anything is running with every page', function () {
    $generation = Generation::factory()->processing()->create();
    Generation::factory()->for($generation->user)->create();
    reportToRecorder($generation, succeededPixelforgeGeneration(['id' => $generation->pixelforge_id]));

    $response = $this->actingAs($generation->user)->get(route('credits.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('notifications.unreadCount', 1)
        ->where('notifications.recent.0.title', 'Image ready')
        ->where('notifications.recent.0.read', false)
        ->where('generationsInProgress', true));
});

it('marks all notifications as read', function () {
    $generation = Generation::factory()->processing()->create();
    reportToRecorder($generation, succeededPixelforgeGeneration(['id' => $generation->pixelforge_id]));
    $user = $generation->user;

    $response = $this->actingAs($user)->from(route('generations.index'))->post(route('notifications.read'));

    $response->assertRedirect(route('generations.index'));
    expect($user->unreadNotifications()->count())->toBe(0);
});

it('only marks the user\'s own notifications as read', function () {
    $generation = Generation::factory()->processing()->create();
    reportToRecorder($generation, succeededPixelforgeGeneration(['id' => $generation->pixelforge_id]));

    $this->actingAs(User::factory()->create())->post(route('notifications.read'));

    expect($generation->user->unreadNotifications()->count())->toBe(1);
});

it('redirects guests to the login page', function () {
    $this->post(route('notifications.read'))->assertRedirect(route('login'));
});
