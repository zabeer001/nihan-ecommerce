<?php

use App\Models\User;

it('refreshes an expired access token within the refresh window', function () {
    config(['jwt.ttl' => 1]);
    $user = User::factory()->create(['role' => 'admin']);
    $token = auth('api')->login($user);
    $this->travel(2)->minutes();
    auth()->forgetGuards();
    $this->withToken($token)->getJson('/api/profile')->assertUnauthorized();
    auth()->forgetGuards();
    $newToken = $this->withToken($token)->postJson('/api/refresh')->assertOk()->json('data.access_token');
    expect($newToken)->toBeString()->not->toBe($token);
    auth()->forgetGuards();
    $this->withToken($newToken)->getJson('/api/profile')->assertOk()->assertJsonPath('data.id', $user->id);
});

it('rejects a refresh after the configured refresh window', function () {
    config(['jwt.ttl' => 1, 'jwt.refresh_ttl' => 2]);
    $token = auth('api')->login(User::factory()->create());
    $this->travel(3)->minutes();
    auth()->forgetGuards();
    $this->withToken($token)->postJson('/api/refresh')->assertUnauthorized();
});

it('rejects invalid tokens and frozen accounts during refresh', function () {
    $this->withToken('invalid')->postJson('/api/refresh')->assertUnauthorized();
    auth()->forgetGuards();
    $user = User::factory()->create(['is_frozen' => true]);
    $token = auth('api')->login($user);
    auth()->forgetGuards();
    $this->withToken($token)->postJson('/api/refresh')->assertForbidden();
});
