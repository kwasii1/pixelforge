<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('adds a credit pack to the balance', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->from(route('credits.index'))->post(route('credits.store'), ['dollars' => 10]);

    $response->assertRedirect(route('credits.index'));
    expect($user->refresh()->balance)->toBe(10_000_000_000);
    $this->assertDatabaseHas('credit_transactions', [
        'user_id' => $user->id,
        'type' => 'purchase',
        'amount' => 10_000_000_000,
    ]);
});

it('rejects amounts that are not one of the packs', function (mixed $dollars) {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('credits.store'), ['dollars' => $dollars]);

    $response->assertInvalid(['dollars']);
    expect($user->refresh()->balance)->toBe(0);
})->with([7, -5, 1000000, 'ten', null]);

it('shows the balance and only the user\'s own activity', function () {
    $user = User::factory()->withCredits(5)->create();
    User::factory()->withCredits(25)->create();

    $response = $this->actingAs($user)->get(route('credits.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('credits/Index')
        ->where('credits.balance', ['exact' => '5.000000000', 'display' => '$5.00'])
        ->has('transactions.data', 1)
        ->where('transactions.data.0.amount.exact', '5.000000000')
        ->where('packs', [5, 10, 25]));
});

it('redirects guests to the login page', function () {
    $this->post(route('credits.store'), ['dollars' => 10])->assertRedirect(route('login'));
});
