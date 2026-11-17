<?php

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

describe('index', function () {
    it('redirects guests to the login page', function () {
        $this->get(route('generations.index'))->assertRedirect(route('login'));
    });

    it('lists only the user\'s own generations, newest first', function () {
        $user = User::factory()->create();
        $older = Generation::factory()->for($user)->succeeded()->create(['created_at' => now()->subHour()]);
        $newer = Generation::factory()->for($user)->create();
        Generation::factory()->create();

        $response = $this->actingAs($user)->get(route('generations.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('generations/Index')
            ->has('generations.data', 2)
            ->where('generations.data.0.id', $newer->id)
            ->where('generations.data.1.id', $older->id)
            ->where('hasGenerationsInProgress', true));
    });

    it('shows the estimate, the exact charge and how it was worked out for a succeeded generation', function () {
        $generation = Generation::factory()->succeeded()->create();

        $response = $this->actingAs($generation->user)->get(route('generations.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('generations.data.0.estimate.max', ['exact' => '0.086343750', 'display' => '$0.0863'])
            ->where('generations.data.0.price', ['exact' => '0.070700000', 'display' => '$0.0707'])
            ->where('generations.data.0.costBreakdown.lines.2', [
                'label' => 'Image output',
                'tokens' => 1100,
                'ratePerMillion' => '40.00',
                'cost' => ['exact' => '0.044000000', 'display' => '$0.0440'],
            ])
            ->where('generations.data.0.costBreakdown.providerCost.exact', '0.056560000')
            ->where('generations.data.0.costBreakdown.markup.exact', '0.014140000')
            ->where('hasGenerationsInProgress', false));
    });

    it('shows a failed generation as free with a readable reason', function () {
        $generation = Generation::factory()->failed()->create(['error_code' => 'internal_error']);

        $response = $this->actingAs($generation->user)->get(route('generations.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('generations.data.0.status', 'failed')
            ->where('generations.data.0.price.exact', '0.000000000')
            ->where('generations.data.0.failureReason', 'Pixelforge had an internal error. Trying again usually works.')
            ->where('generations.data.0.costBreakdown', null));
    });

    it('labels a generation waiting on Pixelforge capacity', function () {
        $generation = Generation::factory()->create(['submission_attempts' => 2]);

        $response = $this->actingAs($generation->user)->get(route('generations.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('generations.data.0.statusLabel', 'Waiting for capacity'));
    });

    it('marks an image whose URL has expired', function () {
        $generation = Generation::factory()->succeeded()->create(['image_url_expires_at' => now()->subMinute()]);

        $response = $this->actingAs($generation->user)->get(route('generations.index'));

        $response->assertInertia(fn (Assert $page) => $page->where('generations.data.0.imageExpired', true));
    });
});

describe('create', function () {
    it('redirects guests to the login page', function () {
        $this->get(route('generations.create'))->assertRedirect(route('login'));
    });

    it('renders the generate page with pricing in nanodollars', function () {
        fakePixelforgePricing();

        $response = $this->actingAs(User::factory()->create())->get(route('generations.create'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('generations/Create')
            ->where('pricing.ratesPerMillion.output', 40_000_000_000)
            ->where('pricing.textTokenVarianceBasisPoints', 1500)
            ->where('pricing.markup', ['numerator' => 5, 'denominator' => 4])
            ->where('defaults.size', '1024x1024')
            ->where('defaults.quality', 'medium')
            ->has('sizes', 3)
            ->has('qualities', 3));
    });

    it('prefills the form from one of the user\'s generations', function () {
        fakePixelforgePricing();
        $generation = Generation::factory()->failed()->create([
            'prompt' => 'A red fox',
            'reference_images' => ['https://example.com/a.png'],
        ]);

        $response = $this->actingAs($generation->user)->get(route('generations.create', ['from' => $generation->id]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('defaults.prompt', 'A red fox')
            ->where('defaults.reference_images', ['https://example.com/a.png']));
    });

    it('does not prefill from another user\'s generation', function () {
        fakePixelforgePricing();
        $generation = Generation::factory()->create(['prompt' => 'Private prompt']);

        $response = $this->actingAs(User::factory()->create())->get(route('generations.create', ['from' => $generation->id]));

        $response->assertInertia(fn (Assert $page) => $page->where('defaults.prompt', ''));
    });
});

describe('store', function () {
    it('records the generation with its estimate and queues it for submission', function () {
        Queue::fake();
        fakePixelforgePricing();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('generations.store'), [
            'prompt' => str_repeat('a', 400),
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => ['https://example.com/a.png', '', 'https://example.com/b.png'],
        ]);

        $response->assertRedirect(route('generations.index'));
        $generation = $user->generations()->sole();
        expect($generation)
            ->status->toBe(GenerationStatus::Queued)
            ->reference_images->toBe(['https://example.com/a.png', 'https://example.com/b.png'])
            ->estimated_max_cost->toBe(373_875_000)
            ->estimated_max_price->toBe(467_343_750)
            ->estimated_min_price->toBe(357_781_250);
        Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job): bool => $job->generation->is($generation));
    });

    it('rejects invalid input', function (array $input, string $field) {
        Queue::fake();

        $response = $this->actingAs(User::factory()->create())->post(route('generations.store'), array_replace([
            'prompt' => 'A cat',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
        ], $input));

        $response->assertInvalid([$field]);
        expect(Generation::count())->toBe(0);
        Queue::assertNothingPushed();
    })->with([
        'missing prompt' => [['prompt' => ''], 'prompt'],
        'prompt too long' => [['prompt' => str_repeat('a', 4001)], 'prompt'],
        'unknown size' => [['size' => '512x512'], 'size'],
        'unknown quality' => [['quality' => 'ultra'], 'quality'],
        'too many references' => [['reference_images' => array_map(fn (int $i): string => "https://example.com/{$i}.png", range(1, 5))], 'reference_images'],
        'non-http reference' => [['reference_images' => ['ftp://example.com/a.png']], 'reference_images.0'],
        'not a URL' => [['reference_images' => ['not a url']], 'reference_images.0'],
        'duplicate reference' => [['reference_images' => ['https://example.com/a.png', 'https://example.com/a.png']], 'reference_images.0'],
    ]);

    it('redirects guests to the login page', function () {
        $this->post(route('generations.store'), ['prompt' => 'A cat'])->assertRedirect(route('login'));
    });
});
