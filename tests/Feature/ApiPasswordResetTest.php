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


it('delivers a reset email containing the working reset page link', function () {
    config(['mail.default' => 'array']);
    $user = User::factory()->create();

    $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

    $messages = app('mailer')->getSymfonyTransport()->messages();
    expect($messages)->toHaveCount(1);
    $message = $messages->first()->getOriginalMessage();
    expect($message->getTo()[0]->getAddress())->toBe($user->email);
    expect($message->getHtmlBody())->toContain('/reset-password/')->toContain(urlencode($user->email));
    expect(\Illuminate\Support\Facades\Route::has('password.reset'))->toBeTrue();
});

it('rejects an invalid reset token and leaves the password unchanged', function () {
    $user = User::factory()->create(['password' => 'old-password']);
    $this->postJson('/api/reset-password', [
        'email' => $user->email, 'token' => 'invalid-token',
        'password' => 'new-password', 'password_confirmation' => 'new-password',
    ])->assertUnprocessable();
    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

it('returns the same reset response for an unknown email without sending mail', function () {
    Notification::fake();
    $this->postJson('/api/forgot-password', ['email' => 'unknown@example.com'])
        ->assertOk()->assertJsonPath('message', 'If an account exists for that email, a password reset link has been sent.');
    Notification::assertNothingSent();
});
