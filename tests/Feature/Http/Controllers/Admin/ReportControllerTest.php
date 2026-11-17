<?php

use App\Models\Generation;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-05 12:00:00');
});

/**
 * Fake GET /v1/usage with the given cost per day (other days are zero).
 *
 * @param  array<string, string>  $costs
 */
function fakePixelforgeUsage(array $costs): void
{
    Http::fake(['pixelforge.test/v1/usage*' => function (Request $request) use ($costs) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $days = [];

        for ($day = strtotime($query['start_date']); $day <= strtotime($query['end_date']); $day += 86400) {
            $date = date('Y-m-d', $day);
            $days[] = ['date' => $date, 'generations' => 0, 'input_text_tokens' => 0, 'input_image_tokens' => 0, 'output_tokens' => 0, 'reasoning_tokens' => 0, 'cost_usd' => $costs[$date] ?? '0.000000000'];
        }

        return Http::response(['object' => 'usage', 'data' => $days, 'total' => []]);
    }]);
}

it('forbids users who are not operators with 403', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.report'))->assertForbidden();
});

it('redirects guests to the login page', function () {
    $this->get(route('admin.report'))->assertRedirect(route('login'));
});

it('reports spend, provider cost and profit by the UTC day each generation completed', function () {
    fakePixelforgeUsage(['2026-11-04' => '0.056560000', '2026-11-05' => '0.056560000']);
    Generation::factory()->succeeded()->create([
        'created_at' => '2026-11-03 23:59:50',
        'completed_at' => '2026-11-04 00:00:10',
    ]);
    Generation::factory()->succeeded()->create(['completed_at' => '2026-11-05 09:00:00']);
    Generation::factory()->failed()->create(['completed_at' => '2026-11-05 09:00:00']);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.report', ['start_date' => '2026-11-03', 'end_date' => '2026-11-05']));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/Report')
        ->has('days', 3)
        ->where('days.0.date', '2026-11-05')
        ->where('days.0.generations', 1)
        ->where('days.1.date', '2026-11-04')
        ->where('days.1.generations', 1)
        ->where('days.1.spend.exact', '0.070700000')
        ->where('days.1.providerCost.exact', '0.056560000')
        ->where('days.1.profit.exact', '0.014140000')
        ->where('days.1.difference.exact', '0.000000000')
        ->where('days.2.generations', 0)
        ->where('totals.generations', 2)
        ->where('totals.providerCost.exact', '0.113120000')
        ->where('totals.difference.exact', '0.000000000'));
});

it('flags a day where our provider cost differs from Pixelforge\'s', function () {
    fakePixelforgeUsage(['2026-11-05' => '0.056560001']);
    Generation::factory()->succeeded()->create(['completed_at' => '2026-11-05 09:00:00']);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.report', ['start_date' => '2026-11-05', 'end_date' => '2026-11-05']));

    $response->assertInertia(fn (Assert $page) => $page->where('days.0.difference.exact', '-0.000000001'));
});

it('reports spend per user', function () {
    fakePixelforgeUsage([]);
    $user = User::factory()->create(['email' => 'ada@example.com']);
    Generation::factory()->for($user)->succeeded()->count(2)->create(['completed_at' => '2026-11-05 09:00:00']);

    $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.report'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('users', 1)
        ->where('users.0.email', 'ada@example.com')
        ->where('users.0.generations', 2)
        ->where('users.0.spend.exact', '0.141400000'));
});

it('still shows our figures when Pixelforge\'s usage report is unavailable', function () {
    Http::fake(['pixelforge.test/v1/usage*' => Http::response(pixelforgeError('overloaded', 'api_error'), 503)]);
    Generation::factory()->succeeded()->create(['completed_at' => '2026-11-05 09:00:00']);

    $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.report'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('totals.generations', 1)
        ->where('totals.pixelforgeCost', null)
        ->whereType('pixelforgeUsageError', 'string'));
});

it('limits the range to the 92 days Pixelforge can report', function () {
    fakePixelforgeUsage([]);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.report', ['start_date' => '2026-01-01', 'end_date' => '2026-11-05']));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('filters.start_date', '2026-08-06')
        ->has('days', 92));
});

it('rejects a range that ends in the future', function () {
    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.report', ['start_date' => '2026-11-01', 'end_date' => '2026-11-06']));

    $response->assertInvalid(['end_date']);
});
