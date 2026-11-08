<?php

use App\Models\Tape;
use App\Models\TapeComment;
use App\Models\TapeCommentReaction;
use App\Models\TapeCommentReply;
use App\Models\User;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

it('lists tape comments publicly newest first', function () {
    $user = User::factory()->create();
    $tape = Tape::query()->create(['title' => 'Feedback Tape']);

    $oldComment = TapeComment::query()->create([
        'tape_id' => $tape->id,
        'user_id' => $user->id,
        'sentiment' => 'good',
        'rating' => 4,
        'comment' => 'Older note',
    ]);
    $oldComment->forceFill([
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ])->saveQuietly();

    $newComment = TapeComment::query()->create([
        'tape_id' => $tape->id,
        'user_id' => $user->id,
        'sentiment' => 'bad',
        'rating' => 2,
        'comment' => 'Newer note',
    ]);

    $response = $this->getJson("/api/tapes/{$tape->id}/comments");

    $response
        ->assertOk()
        ->assertJsonPath('data.0.id', $newComment->id)
        ->assertJsonPath('data.0.user.name', $user->name)
        ->assertJsonPath('data.1.id', $oldComment->id);
});

it('returns only the latest seven tape comments', function () {
    $user = User::factory()->create();
    $tape = Tape::query()->create(['title' => 'Feedback Tape']);

    for ($index = 1; $index <= 10; $index += 1) {
        $comment = TapeComment::query()->create([
            'tape_id' => $tape->id,
            'user_id' => $user->id,
            'sentiment' => 'neutral',
            'rating' => null,
            'comment' => "Comment {$index}",
        ]);

        $comment->forceFill([
            'created_at' => now()->addSeconds($index),
            'updated_at' => now()->addSeconds($index),
        ])->saveQuietly();
    }

    $response = $this->getJson("/api/tapes/{$tape->id}/comments");

    $response
        ->assertOk()
        ->assertJsonCount(7, 'data')
        ->assertJsonPath('data.0.comment', 'Comment 10')
        ->assertJsonPath('data.6.comment', 'Comment 4');
});

it('prevents guests from posting tape comments', function () {
    $tape = Tape::query()->create(['title' => 'Feedback Tape']);

    $response = $this->postJson("/api/tapes/{$tape->id}/comments", [
        'sentiment' => 'good',
        'rating' => 5,
        'comment' => 'Looks right to me.',
    ]);

    $response->assertUnauthorized();
});

it('allows authenticated users to post valid tape comments', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'is_frozen' => false,
    ]);
    $tape = Tape::query()->create(['title' => 'Feedback Tape']);
    $token = JWTAuth::fromUser($user);

    $response = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/tapes/{$tape->id}/comments", [
            'sentiment' => 'neutral',
            'rating' => 3,
            'comment' => '  Needs another source.  ',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.tape_id', $tape->id)
        ->assertJsonPath('data.user_id', $user->id)
        ->assertJsonPath('data.sentiment', 'neutral')
        ->assertJsonPath('data.rating', 3)
        ->assertJsonPath('data.comment', 'Needs another source.');

    expect($tape->comments()->count())->toBe(1);
    expect($user->tapeComments()->count())->toBe(1);
});

it('stores valid mentions on tape comments and rejects invalid mentions', function () {
    $user = User::factory()->create(['role' => 'user', 'is_frozen' => false]);
    $mentionedUser = User::factory()->create();
    $tape = Tape::query()->create(['title' => 'Feedback Tape']);
    $token = JWTAuth::fromUser($user);

    $response = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/tapes/{$tape->id}/comments", [
            'sentiment' => 'good',
            'comment' => 'Thanks @someone.',
            'mention_user_ids' => [$mentionedUser->id],
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.mentions.0.mentioned_user_id', $mentionedUser->id)
        ->assertJsonPath('data.mentions.0.user.name', $mentionedUser->name);

    $invalidResponse = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/tapes/{$tape->id}/comments", [
            'sentiment' => 'good',
            'comment' => 'Bad mention.',
            'mention_user_ids' => [999999],
        ]);

    $invalidResponse
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mention_user_ids.0']);
});

it('allows normal users to create replies before five total normal replies exist', function () {
    $user = User::factory()->create(['role' => 'user', 'is_frozen' => false]);
    $replyUser = User::factory()->create(['role' => 'user', 'is_frozen' => false]);
    $mentionedUser = User::factory()->create();
    $tape = Tape::query()->create(['title' => 'Feedback Tape']);
    $comment = TapeComment::query()->create([
        'tape_id' => $tape->id,
        'user_id' => $user->id,
        'sentiment' => 'neutral',
        'comment' => 'Parent comment',
    ]);

    for ($index = 1; $index <= 4; $index += 1) {
        TapeCommentReply::query()->create([
            'tape_comment_id' => $comment->id,
            'user_id' => $replyUser->id,
            'reply' => "Reply {$index}",
        ]);
    }

    $token = JWTAuth::fromUser($replyUser);

    $response = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/tape-comments/{$comment->id}/replies", [
            'reply' => 'Reply five',
            'mention_user_ids' => [$mentionedUser->id],
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.reply', 'Reply five')
        ->assertJsonPath('data.mentions.0.mentioned_user_id', $mentionedUser->id);

    expect($comment->replies()->count())->toBe(5);
});

it('blocks normal users after five total normal replies exist', function () {
    $user = User::factory()->create(['role' => 'user', 'is_frozen' => false]);
    $replyUser = User::factory()->create(['role' => 'user', 'is_frozen' => false]);
    $tape = Tape::query()->create(['title' => 'Feedback Tape']);
    $comment = TapeComment::query()->create([
        'tape_id' => $tape->id,
        'user_id' => $user->id,
        'sentiment' => 'neutral',
        'comment' => 'Parent comment',
    ]);

    for ($index = 1; $index <= 5; $index += 1) {
        TapeCommentReply::query()->create([
            'tape_comment_id' => $comment->id,
            'user_id' => $replyUser->id,
            'reply' => "Reply {$index}",
        ]);
    }

    $token = JWTAuth::fromUser($replyUser);

    $response = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/tape-comments/{$comment->id}/replies", [
            'reply' => 'Reply six',
        ]);

    $response->assertUnprocessable();
});

it('allows admins to reply after the normal reply cap', function () {
    $user = User::factory()->create(['role' => 'user', 'is_frozen' => false]);
    $admin = User::factory()->create(['role' => 'admin', 'is_frozen' => false]);
    $tape = Tape::query()->create(['title' => 'Feedback Tape']);
    $comment = TapeComment::query()->create([
        'tape_id' => $tape->id,
        'user_id' => $user->id,
        'sentiment' => 'neutral',
        'comment' => 'Parent comment',
    ]);

    for ($index = 1; $index <= 5; $index += 1) {
        TapeCommentReply::query()->create([
            'tape_comment_id' => $comment->id,
            'user_id' => $user->id,
            'reply' => "Reply {$index}",
        ]);
    }

    $token = JWTAuth::fromUser($admin);

    $response = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/tape-comments/{$comment->id}/replies", [
            'reply' => 'Admin reply',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.reply', 'Admin reply')
        ->assertJsonPath('data.user.id', $admin->id);
});

it('allows superadmins to reply after the normal reply cap', function () {
    $user = User::factory()->create(['role' => 'user', 'is_frozen' => false]);
    $superadmin = User::factory()->create(['role' => 'superadmin', 'is_frozen' => false]);
    $tape = Tape::query()->create(['title' => 'Feedback Tape']);
    $comment = TapeComment::query()->create([
        'tape_id' => $tape->id,
        'user_id' => $user->id,
        'sentiment' => 'neutral',
        'comment' => 'Parent comment',
    ]);

    for ($index = 1; $index <= 5; $index += 1) {
        TapeCommentReply::query()->create([
            'tape_comment_id' => $comment->id,
            'user_id' => $user->id,
            'reply' => "Reply {$index}",
        ]);
    }

    $superadminToken = JWTAuth::fromUser($superadmin);

    $superadminResponse = $this
        ->withHeader('Authorization', "Bearer {$superadminToken}")
        ->postJson("/api/tape-comments/{$comment->id}/replies", [
            'reply' => 'Superadmin reply',
        ]);

    $superadminResponse
        ->assertCreated()
        ->assertJsonPath('data.reply', 'Superadmin reply')
        ->assertJsonPath('data.user.id', $superadmin->id);
});

it('allows users to add change and remove one reaction per comment', function () {
    $user = User::factory()->create(['role' => 'user', 'is_frozen' => false]);
    $tape = Tape::query()->create(['title' => 'Feedback Tape']);
    $comment = TapeComment::query()->create([
        'tape_id' => $tape->id,
        'user_id' => $user->id,
        'sentiment' => 'neutral',
        'comment' => 'Parent comment',
    ]);
    $token = JWTAuth::fromUser($user);

    $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/comment-reactions', [
            'target_type' => 'comment',
            'target_id' => $comment->id,
            'reaction' => 'like',
        ])
        ->assertOk()
        ->assertJsonPath('data.viewer_reaction', 'like')
        ->assertJsonPath('data.reaction_counts.like', 1);

    $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/comment-reactions', [
            'target_type' => 'comment',
            'target_id' => $comment->id,
            'reaction' => 'love',
        ])
        ->assertOk()
        ->assertJsonPath('data.viewer_reaction', 'love')
        ->assertJsonPath('data.reaction_counts.like', 0)
        ->assertJsonPath('data.reaction_counts.love', 1);

    expect(TapeCommentReaction::query()->count())->toBe(1);

    $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson('/api/comment-reactions', [
            'target_type' => 'comment',
            'target_id' => $comment->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.viewer_reaction', null)
        ->assertJsonPath('data.reaction_counts.love', 0);
});

it('allows users to add change and remove one reaction per reply', function () {
    $user = User::factory()->create(['role' => 'user', 'is_frozen' => false]);
    $tape = Tape::query()->create(['title' => 'Feedback Tape']);
    $comment = TapeComment::query()->create([
        'tape_id' => $tape->id,
        'user_id' => $user->id,
        'sentiment' => 'neutral',
        'comment' => 'Parent comment',
    ]);
    $reply = $comment->replies()->create([
        'user_id' => $user->id,
        'reply' => 'Reply body',
    ]);
    $token = JWTAuth::fromUser($user);

    $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/comment-reactions', [
            'target_type' => 'reply',
            'target_id' => $reply->id,
            'reaction' => 'angry',
        ])
        ->assertOk()
        ->assertJsonPath('data.viewer_reaction', 'angry')
        ->assertJsonPath('data.reaction_counts.angry', 1);

    $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/comment-reactions', [
            'target_type' => 'reply',
            'target_id' => $reply->id,
            'reaction' => 'laugh',
        ])
        ->assertOk()
        ->assertJsonPath('data.viewer_reaction', 'laugh')
        ->assertJsonPath('data.reaction_counts.angry', 0)
        ->assertJsonPath('data.reaction_counts.laugh', 1);

    expect(TapeCommentReaction::query()->count())->toBe(1);

    $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson('/api/comment-reactions', [
            'target_type' => 'reply',
            'target_id' => $reply->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.viewer_reaction', null)
        ->assertJsonPath('data.reaction_counts.laugh', 0);
});

it('rejects invalid mentions on tape comment replies', function () {
    $user = User::factory()->create(['role' => 'user', 'is_frozen' => false]);
    $tape = Tape::query()->create(['title' => 'Feedback Tape']);
    $comment = TapeComment::query()->create([
        'tape_id' => $tape->id,
        'user_id' => $user->id,
        'sentiment' => 'neutral',
        'comment' => 'Parent comment',
    ]);
    $token = JWTAuth::fromUser($user);

    $response = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/tape-comments/{$comment->id}/replies", [
            'reply' => 'Reply with bad mention.',
            'mention_user_ids' => [999999],
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mention_user_ids.0']);
});

it('returns comments with replies reactions mentions and viewer reaction', function () {
    $author = User::factory()->create(['role' => 'user', 'is_frozen' => false]);
    $viewer = User::factory()->create(['role' => 'user', 'is_frozen' => false]);
    $mentionedUser = User::factory()->create();
    $tape = Tape::query()->create(['title' => 'Feedback Tape']);
    $comment = TapeComment::query()->create([
        'tape_id' => $tape->id,
        'user_id' => $author->id,
        'sentiment' => 'good',
        'rating' => 5,
        'comment' => 'Parent comment',
    ]);

    $comment->mentions()->create([
        'mentionable_type' => 'comment',
        'mentioned_user_id' => $mentionedUser->id,
    ]);

    $reply = $comment->replies()->create([
        'user_id' => $viewer->id,
        'reply' => 'A reply',
    ]);

    $reply->mentions()->create([
        'mentionable_type' => 'reply',
        'mentioned_user_id' => $author->id,
    ]);

    TapeCommentReaction::query()->create([
        'user_id' => $viewer->id,
        'target_type' => 'comment',
        'target_id' => $comment->id,
        'reaction' => 'gratitude',
    ]);

    TapeCommentReaction::query()->create([
        'user_id' => $author->id,
        'target_type' => 'reply',
        'target_id' => $reply->id,
        'reaction' => 'laugh',
    ]);

    $token = JWTAuth::fromUser($viewer);

    $response = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/tapes/{$tape->id}/comments");

    $response
        ->assertOk()
        ->assertJsonPath('data.0.id', $comment->id)
        ->assertJsonPath('data.0.viewer_reaction', 'gratitude')
        ->assertJsonPath('data.0.reaction_counts.gratitude', 1)
        ->assertJsonPath('data.0.mentions.0.mentioned_user_id', $mentionedUser->id)
        ->assertJsonPath('data.0.replies.0.id', $reply->id)
        ->assertJsonPath('data.0.replies.0.reaction_counts.laugh', 1)
        ->assertJsonPath('data.0.replies.0.mentions.0.mentioned_user_id', $author->id);
});

it('validates tape comment sentiment rating and comment text', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'is_frozen' => false,
    ]);
    $tape = Tape::query()->create(['title' => 'Feedback Tape']);
    $token = JWTAuth::fromUser($user);

    $response = $this
        ->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/tapes/{$tape->id}/comments", [
            'sentiment' => 'excellent',
            'rating' => 6,
            'comment' => '   ',
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sentiment', 'rating', 'comment']);
});
