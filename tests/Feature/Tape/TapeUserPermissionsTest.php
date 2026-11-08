<?php

use App\Models\Tape;
use App\Models\User;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

it('allows public user accounts to create tapes', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'is_frozen' => false,
    ]);

    $token = JWTAuth::fromUser($user);

    $response = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/tapes', [
            'title' => 'Community Submission',
            'year' => '1994',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.user_id', $user->id)
        ->assertJsonPath('data.title', 'Community Submission');
});

it('prevents public user accounts from editing tapes', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'is_frozen' => false,
    ]);

    $tape = Tape::query()->create([
        'title' => 'Original Title',
        'qa_checked' => '0',
    ]);

    $token = JWTAuth::fromUser($user);

    $response = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/tapes/{$tape->id}", [
            'title' => 'Blocked Update',
        ]);

    $response->assertForbidden();

    expect($tape->fresh()->title)->toBe('Original Title');
});
