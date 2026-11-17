<?php

use App\Models\User;

it('lets users who have not verified their email use the app', function (string $route) {
    fakePixelforgePricing();
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route($route));

    $response->assertOk();
})->with(['generations.create', 'generations.index', 'credits.index']);

it('sends a new user straight to the generate page after registering', function () {
    fakePixelforgePricing();

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->followRedirects($response)->assertOk();
});
