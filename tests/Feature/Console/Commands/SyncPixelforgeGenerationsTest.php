<?php

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('records the result of a generation whose webhook never arrived', function () {
    $generation = Generation::factory()->processing()->create(['updated_at' => now()->subMinutes(5)]);
    Http::fake(["pixelforge.test/v1/generations/{$generation->pixelforge_id}" => Http::response(
        succeededPixelforgeGeneration(['id' => $generation->pixelforge_id]),
    )]);

    $this->artisan('pixelforge:sync-generations')->assertSuccessful();

    expect($generation->refresh())
        ->status->toBe(GenerationStatus::Succeeded)
        ->price->toBe(381_887_500);
});

it('charges once however many times it runs', function () {
    $generation = Generation::factory()->processing()->create(['updated_at' => now()->subMinutes(5)]);
    Http::fake(['pixelforge.test/*' => Http::response(succeededPixelforgeGeneration(['id' => $generation->pixelforge_id]))]);

    $this->artisan('pixelforge:sync-generations');
    $this->travel(5)->minutes();
    $this->artisan('pixelforge:sync-generations');

    expect($generation->refresh()->price)->toBe(381_887_500);
});

it('leaves recently updated and finished generations alone', function () {
    Generation::factory()->processing()->create();
    Generation::factory()->succeeded()->create(['updated_at' => now()->subHour()]);

    $this->artisan('pixelforge:sync-generations')->assertSuccessful();

    Http::assertNothingSent();
});

it('stops polling for the rest of the run when rate limited', function () {
    Generation::factory()->processing()->count(3)->create(['updated_at' => now()->subMinutes(5)]);
    Http::fake(['pixelforge.test/*' => Http::response(pixelforgeError('rate_limit_exceeded'), 429, ['Retry-After' => '10'])]);

    $this->artisan('pixelforge:sync-generations')->assertSuccessful();

    Http::assertSentCount(1);
});

it('re-queues a generation whose submission job was lost', function () {
    Queue::fake();
    $generation = Generation::factory()->create(['updated_at' => now()->subMinutes(3)]);

    $this->artisan('pixelforge:sync-generations')->assertSuccessful();

    Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job): bool => $job->generation->is($generation));
});

it('fails a generation for free once its submission window has passed', function () {
    Queue::fake();
    $generation = Generation::factory()->create([
        'created_at' => now()->subMinutes(30),
        'updated_at' => now()->subMinutes(20),
    ]);

    $this->artisan('pixelforge:sync-generations')->assertSuccessful();

    expect($generation->refresh())
        ->status->toBe(GenerationStatus::Failed)
        ->price->toBe(0);
    Queue::assertNothingPushed();
});
