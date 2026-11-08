<?php

use App\Models\Tape;
use App\Models\User;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

it('prevents editors from updating QA checked tapes', function () {
    $editor = User::factory()->create([
        'role' => 'editor',
    ]);

    $tape = Tape::query()->create([
        'title' => 'Original Title',
        'qa_checked' => '1',
    ]);

    $token = JWTAuth::fromUser($editor);

    $response = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/tapes/{$tape->id}", [
            'title' => 'Blocked Update',
        ]);

    $response
        ->assertForbidden()
        ->assertJson([
            'status' => 'error',
            'message' => 'This tape has been QA checked and can only be edited by admins.',
        ]);

    expect($tape->fresh()->title)->toBe('Original Title');
});

it('allows admins to update QA checked tapes', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $tape = Tape::query()->create([
        'title' => 'Original Title',
        'qa_checked' => '1',
    ]);

    $token = JWTAuth::fromUser($admin);

    $response = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/tapes/{$tape->id}", [
            'title' => 'Allowed Update',
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'status' => 'success',
            'message' => 'Tape updated successfully.',
        ]);

    expect($tape->fresh()->title)->toBe('Allowed Update');
});
