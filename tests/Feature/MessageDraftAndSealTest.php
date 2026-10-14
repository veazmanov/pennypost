<?php

use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;
/**
 * Message::nextBatchFor() defaults to Laravel's now() (mutable Carbon),
 * while Message::canUnseal() calls CarbonImmutable::now() directly.
 * Carbon and CarbonImmutable each keep their own independent "testNow",
 * so both must be frozen together or one of the two checks will silently
 * fall back to the real wall-clock time.
 */
function freezeTimeAt(string $datetime): void
{
    Carbon::setTestNow(Carbon::parse($datetime, 'UTC'));
    CarbonImmutable::setTestNow(CarbonImmutable::parse($datetime, 'UTC'));
}

afterEach(function () {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

// --- Saving and editing drafts -------------------------------------------------

test('a user can save a letter as a draft without a recipient', function () {
    $sender = User::factory()->create();

    $response = $this->actingAs($sender)->post('/messages', [
        'intent' => 'draft',
        'body' => 'Just getting my thoughts down...',
    ]);

    $draft = Message::first();

    $response->assertRedirect(route('messages.edit', $draft));
    $response->assertSessionHas('status', 'draft-saved');

    expect($draft->is_draft)->toBeTrue();
    expect($draft->sender_id)->toBe($sender->id);
    expect($draft->recipient_id)->toBeNull();
    expect($draft->scheduled_for)->toBeNull();
    expect($draft->sent_at)->toBeNull();
});

test('a draft can be saved with no body and no recipient at all', function () {
    $sender = User::factory()->create();

    $response = $this->actingAs($sender)->post('/messages', [
        'intent' => 'draft',
    ]);

    $response->assertSessionHasNoErrors();

    $draft = Message::first();
    expect($draft->is_draft)->toBeTrue();
    expect($draft->body)->toBe('');
});

test('a user can return to a draft later and update its recipient and body', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $draft = Message::factory()->for($sender, 'sender')->draft()->create(['body' => 'first pass']);

    $response = $this->actingAs($sender)->put("/messages/{$draft->id}", [
        'intent' => 'draft',
        'recipient_id' => $recipient->id,
        'body' => 'a much better version',
    ]);

    $response->assertRedirect(route('messages.edit', $draft));
    $response->assertSessionHas('status', 'draft-saved');

    $draft->refresh();
    expect($draft->is_draft)->toBeTrue();
    expect($draft->recipient_id)->toBe($recipient->id);
    expect($draft->body)->toBe('a much better version');
});

test('a user cannot view, edit, or overwrite someone elses draft', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $draft = Message::factory()->for($owner, 'sender')->draft()->create(['body' => 'private thoughts']);

    $this->actingAs($intruder)->get("/messages/{$draft->id}/edit")->assertNotFound();

    $this->actingAs($intruder)->put("/messages/{$draft->id}", [
        'intent' => 'draft',
        'body' => 'hijacked',
    ])->assertNotFound();

    expect($draft->fresh()->body)->toBe('private thoughts');
});

test('a user can delete their own draft', function () {
    $sender = User::factory()->create();
    $draft = Message::factory()->for($sender, 'sender')->draft()->create();

    $response = $this->actingAs($sender)->delete("/messages/{$draft->id}");

    $response->assertRedirect(route('messages.drafts'));
    $response->assertSessionHas('status', 'draft-deleted');
    $this->assertDatabaseMissing('messages', ['id' => $draft->id]);
});

test('a sealed letter cannot be deleted through the draft delete route', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $sealed = Message::factory()->for($sender, 'sender')->for($recipient, 'recipient')->create();

    $this->actingAs($sender)->delete("/messages/{$sealed->id}")->assertNotFound();

    $this->assertDatabaseHas('messages', ['id' => $sealed->id]);
});

// --- Sealing (sending) a draft --------------------------------------------------

test('sealing a draft requires a recipient and a body', function () {
    $sender = User::factory()->create();
    $draft = Message::factory()->for($sender, 'sender')->draft()->create(['recipient_id' => null, 'body' => '']);

    $response = $this->actingAs($sender)->put("/messages/{$draft->id}", [
        'intent' => 'send',
    ]);

    $response->assertSessionHasErrors(['recipient_id', 'body']);
    expect($draft->fresh()->is_draft)->toBeTrue();
});

test('a letter can be sent directly without ever having been saved as a draft', function () {
    freezeTimeAt('2026-08-10 09:00:00');

    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    $response = $this->actingAs($sender)->post('/messages', [
        'intent' => 'send',
        'recipient_id' => $recipient->id,
        'body' => 'No detour through drafts.',
    ]);

    $response->assertRedirect(route('correspondence.show', $recipient));

    $message = Message::first();
    expect($message->is_draft)->toBeFalse();
    expect($message->sent_at)->not->toBeNull();
});

// --- Listing pages ---------------------------------------------------------------

test('the drafts index only shows the current users own drafts', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $mine = Message::factory()->for($user, 'sender')->draft()->create();
    Message::factory()->for($user, 'sender')->create(); // sealed, should not appear
    Message::factory()->for($other, 'sender')->draft()->create(); // someone else's draft

    $response = $this->actingAs($user)->get('/messages/drafts');

    $response->assertOk();
    expect($response->viewData('drafts')->pluck('id')->all())->toBe([$mine->id]);
});

test('the sender sees their sealed letter and their draft in the thread, the recipient sees neither', function () {
    $user = User::factory()->create();
    $recipient = User::factory()->create();

    Message::factory()->for($user, 'sender')->for($recipient, 'recipient')->create(['body' => 'A real sealed letter.']);
    $draft = Message::factory()->for($user, 'sender')->for($recipient, 'recipient')->draft()->create(['body' => 'Still just a draft.']);

    $this->actingAs($user)->get(route('correspondence.show', $recipient))
        ->assertOk()
        ->assertDontSee('Sealed')
        ->assertSee('in the post, arriving')
        ->assertSee('A real sealed letter.')
        ->assertSee('not sent')
        ->assertSee('Still just a draft.')
        ->assertSee(route('messages.edit', $draft));

    $this->actingAs($recipient)->get(route('correspondence.show', $user))
        ->assertOk()
        ->assertDontSee('A real sealed letter.')
        ->assertDontSee('Still just a draft.');
});


// --- Full end-to-end lifecycle ---------------------------------------------------

it('full draft, edit, and seal lifecycle works end to end — and sending is final', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    // 1. Save a draft.
    $response = $this->actingAs($sender)->post(route('messages.store'), [
        'intent' => 'draft',
        'recipient_id' => $recipient->id,
        'body' => 'First draft of the letter.',
    ]);

    $message = Message::first();

    $response->assertRedirect(route('messages.edit', $message));
    expect($message->is_draft)->toBeTrue();
    expect($message->sender_id)->toBe($sender->id);
    expect($message->recipient_id)->toBe($recipient->id);
    expect($message->sent_at)->toBeNull();
    expect($message->scheduled_for)->toBeNull();

    // 2. Edit the draft — still a draft, content changes.
    $this->actingAs($sender)->put(route('messages.update', $message), [
        'intent' => 'draft',
        'recipient_id' => $recipient->id,
        'body' => 'Revised draft, much better now.',
    ])->assertRedirect(route('messages.edit', $message));

    $message->refresh();
    expect($message->is_draft)->toBeTrue();
    expect($message->body)->toBe('Revised draft, much better now.');

    // 3. Seal & send — this is the point of no return.
    $response = $this->actingAs($sender)->put(route('messages.update', $message), [
        'intent' => 'send',
        'recipient_id' => $recipient->id,
        'body' => 'Revised draft, much better now.',
    ]);

    $message->refresh();
    $response->assertRedirect(route('correspondence.show', $recipient));
    expect($message->is_draft)->toBeFalse();
    expect($message->sent_at)->not->toBeNull();
    expect($message->scheduled_for)->not->toBeNull();

    // 4. Sent letters can no longer be edited...
    $this->actingAs($sender)->get(route('messages.edit', $message))
        ->assertNotFound();

    $this->actingAs($sender)->put(route('messages.update', $message), [
        'intent' => 'draft',
        'body' => 'Trying to sneak an edit in.',
    ])->assertNotFound();

    // 5. ...or deleted...
    $this->actingAs($sender)->delete(route('messages.destroy', $message))
        ->assertNotFound();

    // 6. ...or unsealed back to a draft. There is no unseal route anymore —
    // sending is final, full stop.
    expect(Route::has('messages.unseal'))->toBeFalse();

    $message->refresh();
    expect($message->is_draft)->toBeFalse();
    expect($message->body)->toBe('Revised draft, much better now.');

    // 7. The sender can see their own sealed-but-undelivered letter in the
    // thread (sender-visibility rule), even before delivery.
     $this->actingAs($sender)->get(route('correspondence.show', $recipient))
        ->assertOk()
        ->assertSee('Revised draft, much better now.')
        ->assertDontSee('Sealed');
});
