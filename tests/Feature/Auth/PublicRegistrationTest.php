<?php

use App\Models\User;

it('registers a public account with the user role by default', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Public Member',
        'email' => 'member@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.user.role', 'user')
        ->assertJsonPath('data.user.is_frozen', false);

    $user = User::query()->where('email', 'member@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->role)->toBe('user');
    expect($user->is_frozen)->toBeFalse();
});
