<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('sends a reset password notification through the api', function () {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this->postJson('/api/forgot-password', [
        'email' => $user->email,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('message', 'If an account exists for that email, a password reset link has been sent.');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('resets a password through the api', function () {
    Notification::fake();

    $user = User::factory()->create([
        'password' => Hash::make('old-password'),
    ]);

    $this->postJson('/api/forgot-password', [
        'email' => $user->email,
    ]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->postJson('/api/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Password reset successfully.');

        return true;
    });

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});
