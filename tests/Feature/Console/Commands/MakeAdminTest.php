<?php

use App\Models\User;

it('grants operator access to an existing user', function () {
    $user = User::factory()->create(['email' => 'ops@example.com']);

    $this->artisan('app:make-admin', ['email' => 'ops@example.com'])->assertSuccessful();

    expect($user->refresh()->is_admin)->toBeTrue();
});

it('revokes operator access', function () {
    $user = User::factory()->admin()->create(['email' => 'ops@example.com']);

    $this->artisan('app:make-admin', ['email' => 'ops@example.com', '--revoke' => true])->assertSuccessful();

    expect($user->refresh()->is_admin)->toBeFalse();
});

it('fails for an unknown email address', function () {
    $this->artisan('app:make-admin', ['email' => 'nobody@example.com'])->assertFailed();
});
