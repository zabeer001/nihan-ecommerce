<?php

use App\Models\Tape;
use App\Models\TapeReport;
use App\Models\User;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

function tapeReportToken(User $user): string
{
    return JWTAuth::fromUser($user);
}

it('allows authenticated users to submit tape reports', function () {
    $user = User::factory()->create([
        'email' => 'reporter@example.com',
        'role' => 'user',
        'is_frozen' => false,
    ]);
    $tape = Tape::query()->create([
        'tape_id' => 'TDB-100',
        'title' => 'Reported Tape',
    ]);
    $token = tapeReportToken($user);

    $response = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/tapes/{$tape->id}/reports", [
            'description' => '  The release year looks wrong.  ',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.tape_id', $tape->id)
        ->assertJsonPath('data.user_id', $user->id)
        ->assertJsonPath('data.reporter_email', 'reporter@example.com')
        ->assertJsonPath('data.description', 'The release year looks wrong.')
        ->assertJsonPath('data.status', 'open');

    $this->assertDatabaseHas('tape_reports', [
        'tape_id' => $tape->id,
        'user_id' => $user->id,
        'reporter_email' => 'reporter@example.com',
        'description' => 'The release year looks wrong.',
        'status' => 'open',
    ]);
});

it('prevents guests from submitting tape reports', function () {
    $tape = Tape::query()->create(['title' => 'Reported Tape']);

    $response = $this->postJson("/api/tapes/{$tape->id}/reports", [
        'description' => 'The title is wrong.',
    ]);

    $response->assertUnauthorized();
});

it('validates tape report descriptions', function () {
    $user = User::factory()->create(['role' => 'user', 'is_frozen' => false]);
    $tape = Tape::query()->create(['title' => 'Reported Tape']);
    $token = tapeReportToken($user);

    $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/tapes/{$tape->id}/reports", [
            'description' => '   ',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['description']);

    $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/tapes/{$tape->id}/reports", [
            'description' => str_repeat('a', 1001),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['description']);
});

it('allows admins editors and superadmins to list tape reports', function (string $role) {
    $viewer = User::factory()->create(['role' => $role, 'is_frozen' => false]);
    $reporter = User::factory()->create(['email' => 'alpha@example.com']);
    $tape = Tape::query()->create([
        'tape_id' => 'TDB-111',
        'title' => 'Searchable Tape',
    ]);

    TapeReport::query()->create([
        'tape_id' => $tape->id,
        'user_id' => $reporter->id,
        'reporter_email' => $reporter->email,
        'description' => 'The distributor is wrong.',
        'status' => 'open',
    ]);

    $response = $this
        ->withHeader('Authorization', 'Bearer '.tapeReportToken($viewer))
        ->getJson('/api/tape-reports');

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.reporter_email', 'alpha@example.com')
        ->assertJsonPath('data.items.0.tape.tape_id', 'TDB-111');
})->with(['admin', 'editor', 'superadmin']);

it('prevents normal users from listing tape reports', function () {
    $user = User::factory()->create(['role' => 'user', 'is_frozen' => false]);

    $response = $this
        ->withHeader('Authorization', 'Bearer '.tapeReportToken($user))
        ->getJson('/api/tape-reports');

    $response->assertForbidden();
});

it('filters tape reports by reporter email unified tape search and status', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_frozen' => false]);
    $reporter = User::factory()->create(['email' => 'alpha@example.com']);
    $otherReporter = User::factory()->create(['email' => 'beta@example.com']);
    $targetTape = Tape::query()->create(['tape_id' => 'TDB-111', 'title' => 'Target Tape']);
    $otherTape = Tape::query()->create(['tape_id' => 'TDB-222', 'title' => 'Other Tape']);

    $targetReport = TapeReport::query()->create([
        'tape_id' => $targetTape->id,
        'user_id' => $reporter->id,
        'reporter_email' => 'alpha@example.com',
        'description' => 'The title needs attention.',
        'status' => 'open',
    ]);

    TapeReport::query()->create([
        'tape_id' => $otherTape->id,
        'user_id' => $otherReporter->id,
        'reporter_email' => 'beta@example.com',
        'description' => 'The year needs attention.',
        'status' => 'resolved',
    ]);

    $response = $this
        ->withHeader('Authorization', 'Bearer '.tapeReportToken($admin))
        ->getJson('/api/tape-reports?email=alpha@&tape_id=arget Tap&status=open');

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $targetReport->id)
        ->assertJsonPath('data.items.0.tape.tape_id', 'TDB-111');

    $this
        ->withHeader('Authorization', 'Bearer '.tapeReportToken($admin))
        ->getJson('/api/tape-reports?email=alpha@&tape_id=TDB-111&status=open')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $targetReport->id);
});

it('shows one tape report', function () {
    $editor = User::factory()->create(['role' => 'editor', 'is_frozen' => false]);
    $reporter = User::factory()->create(['email' => 'alpha@example.com']);
    $targetTape = Tape::query()->create(['tape_id' => 'TDB-111', 'title' => 'Target Tape']);
    $targetReport = TapeReport::query()->create([
        'tape_id' => $targetTape->id,
        'user_id' => $reporter->id,
        'reporter_email' => 'alpha@example.com',
        'description' => 'This specific tape needs attention.',
        'status' => 'open',
    ]);

    $response = $this
        ->withHeader('Authorization', 'Bearer '.tapeReportToken($editor))
        ->getJson("/api/tape-reports/{$targetReport->id}");

    $response
        ->assertOk()
        ->assertJsonPath('data.id', $targetReport->id)
        ->assertJsonPath('data.tape.id', $targetTape->id)
        ->assertJsonPath('data.description', 'This specific tape needs attention.');
});

it('allows admins editors and superadmins to resolve and reopen tape reports', function () {
    $editor = User::factory()->create(['role' => 'editor', 'is_frozen' => false]);
    $admin = User::factory()->create(['role' => 'admin', 'is_frozen' => false]);
    $reporter = User::factory()->create();
    $tape = Tape::query()->create(['title' => 'Reported Tape']);
    $report = TapeReport::query()->create([
        'tape_id' => $tape->id,
        'user_id' => $reporter->id,
        'reporter_email' => $reporter->email,
        'description' => 'The UPC is wrong.',
        'status' => 'open',
    ]);

    $this
        ->withHeader('Authorization', 'Bearer '.tapeReportToken($editor))
        ->patchJson("/api/tape-reports/{$report->id}", [
            'status' => 'resolved',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved')
        ->assertJsonPath('data.resolved_by_user_id', $editor->id);

    $this->assertDatabaseHas('tape_reports', [
        'id' => $report->id,
        'status' => 'resolved',
        'resolved_by_user_id' => $editor->id,
    ]);

    $this
        ->withHeader('Authorization', 'Bearer '.tapeReportToken($admin))
        ->patchJson("/api/tape-reports/{$report->id}", [
            'status' => 'open',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.resolved_by_user_id', null);

    $this->assertDatabaseHas('tape_reports', [
        'id' => $report->id,
        'status' => 'open',
        'resolved_by_user_id' => null,
        'resolved_at' => null,
    ]);
});

it('prevents normal users from updating tape reports', function () {
    $user = User::factory()->create(['role' => 'user', 'is_frozen' => false]);
    $reporter = User::factory()->create();
    $tape = Tape::query()->create(['title' => 'Reported Tape']);
    $report = TapeReport::query()->create([
        'tape_id' => $tape->id,
        'user_id' => $reporter->id,
        'reporter_email' => $reporter->email,
        'description' => 'The notes are wrong.',
        'status' => 'open',
    ]);

    $response = $this
        ->withHeader('Authorization', 'Bearer '.tapeReportToken($user))
        ->patchJson("/api/tape-reports/{$report->id}", [
            'status' => 'resolved',
        ]);

    $response->assertForbidden();
});

it('allows admins to delete reports from the inbox', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_frozen' => false]);
    $reporter = User::factory()->create();
    $tape = Tape::query()->create(['title' => 'Reported Tape']);
    $report = TapeReport::query()->create([
        'tape_id' => $tape->id,
        'user_id' => $reporter->id,
        'reporter_email' => $reporter->email,
        'description' => 'Delete me.',
        'status' => 'open',
    ]);

    $this
        ->withHeader('Authorization', 'Bearer '.tapeReportToken($admin))
        ->deleteJson("/api/tape-reports/{$report->id}")
        ->assertOk();

    $this->assertDatabaseMissing('tape_reports', [
        'id' => $report->id,
    ]);
});

it('prevents editors from deleting reports', function () {
    $editor = User::factory()->create(['role' => 'editor', 'is_frozen' => false]);
    $reporter = User::factory()->create();
    $tape = Tape::query()->create(['title' => 'Reported Tape']);
    $report = TapeReport::query()->create([
        'tape_id' => $tape->id,
        'user_id' => $reporter->id,
        'reporter_email' => $reporter->email,
        'description' => 'Do not delete me.',
        'status' => 'open',
    ]);

    $this
        ->withHeader('Authorization', 'Bearer '.tapeReportToken($editor))
        ->deleteJson("/api/tape-reports/{$report->id}")
        ->assertForbidden();
});
