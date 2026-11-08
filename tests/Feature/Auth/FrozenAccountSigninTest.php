<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('prevents frozen users from signing in', function () {
    User::factory()->create([
        'email' => 'frozen@example.com',
        'password' => Hash::make('password123'),
        'role' => 'user',
        'is_frozen' => true,
    ]);

    $response = $this->postJson('/api/signin', [
        'email' => 'frozen@example.com',
        'password' => 'password123',
    ]);

    $response
        ->assertForbidden()
        ->assertJson([
            'message' => 'This account has been frozen. Please contact an administrator.',
        ]);
});
