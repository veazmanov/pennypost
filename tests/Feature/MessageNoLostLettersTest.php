<?php

use App\Models\Message;
use App\Models\User;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

// --- A failed send is saved as a draft ---------------------------------------------

test('a send with no recipient on a new letter is kept as a draft', function () {
    $sender = User::factory()->create();

    $response = $this->actingAs($sender)->post('/messages', [
        'intent' => 'send',
        'body' => 'Please do not lose me.',
    ]);

    $draft = Message::sole();

    $response->assertRedirect(route('messages.edit', $draft));
    $response->assertSessionHasErrors('recipient_id');
    $response->assertSessionHas('status', 'send-failed');
    $response->assertSessionHasInput('body', 'Please do not lose me.');

    expect($draft->is_draft)->toBeTrue();
    expect($draft->sender_id)->toBe($sender->id);
    expect($draft->body)->toBe('Please do not lose me.');
    expect($draft->sent_at)->toBeNull();
});

test('a failed send on an existing draft updates that draft', function () {
    $sender = User::factory()->create();
    $draft = Message::factory()->for($sender, 'sender')->draft()->create(['recipient_id' => null, 'body' => 'old text']);

    $response = $this->actingAs($sender)->put("/messages/{$draft->id}", [
        'intent' => 'send',
        'body' => 'new text',
        'enclosures' => ['https://example.com/ok', 'not-a-url'],
    ]);

    $response->assertRedirect(route('messages.edit', $draft));
    $response->assertSessionHas('status', 'send-failed');
    $response->assertSessionHasErrors(['recipient_id', 'enclosures.1']);

    $draft->refresh();
    expect(Message::count())->toBe(1);
    expect($draft->is_draft)->toBeTrue();
    expect($draft->body)->toBe('new text');
    expect($draft->enclosures)->toBe(['https://example.com/ok']);
});

test('a failed send keeps a valid recipient on the draft', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    $this->actingAs($sender)->post('/messages', [
        'intent' => 'send',
        'recipient_id' => $recipient->id,
        'body' => '',
    ])->assertSessionHasErrors('body');

    expect(Message::sole()->recipient_id)->toBe($recipient->id);
});

// --- Typed names --------------------------------------------------------------------

test('a typed name with exactly one case-insensitive match is resolved and sent', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create(['name' => 'Ada Lovelace']);

    $response = $this->actingAs($sender)->post('/messages', [
        'intent' => 'send',
        'recipient_name' => '  ada LOVELACE ',
        'body' => 'Found you by name.',
    ]);

    $response->assertRedirect(route('correspondence.show', $recipient));

    $message = Message::sole();
    expect($message->is_draft)->toBeFalse();
    expect($message->recipient_id)->toBe($recipient->id);
});

test('an ambiguous typed name is not resolved', function () {
    $sender = User::factory()->create();
    User::factory()->count(2)->create(['name' => 'Sam Smith']);

    $response = $this->actingAs($sender)->post('/messages', [
        'intent' => 'send',
        'recipient_name' => 'Sam Smith',
        'body' => 'Which Sam?',
    ]);

    $response->assertSessionHas('status', 'send-failed');
    $response->assertSessionHasErrors('recipient_id');

    $draft = Message::sole();
    expect($draft->is_draft)->toBeTrue();
    expect($draft->recipient_id)->toBeNull();
    expect($draft->body)->toBe('Which Sam?');
});

test('a typed name never resolves to a deleted member or yourself', function () {
    $sender = User::factory()->create(['name' => 'Me Myself']);
    User::factory()->create(['name' => 'Gone Person'])->delete();

    foreach (['Gone Person', 'Me Myself'] as $name) {
        $this->actingAs($sender)->post('/messages', [
            'intent' => 'send',
            'recipient_name' => $name,
            'body' => 'Hello?',
        ])->assertSessionHas('status', 'send-failed');
    }

    expect(Message::where('is_draft', false)->count())->toBe(0);
});

// --- Draft saves never fail over the recipient ----------------------------------------

test('a draft save with an unresolved name keeps the body and warns', function () {
    $sender = User::factory()->create();

    $response = $this->actingAs($sender)->post('/messages', [
        'intent' => 'draft',
        'recipient_name' => 'Nobody Here',
        'body' => 'Still worth keeping.',
    ]);

    $draft = Message::sole();

    $response->assertRedirect(route('messages.edit', $draft));
    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('recipientWarning', "We couldn't find 'Nobody Here' — pick them from the suggestions.");
    $response->assertSessionHasInput('recipient_name', 'Nobody Here');

    expect($draft->body)->toBe('Still worth keeping.');
    expect($draft->recipient_id)->toBeNull();

    $this->actingAs($sender)->get(route('messages.edit', $draft))
        ->assertOk()
        ->assertSee("We couldn&#039;t find &#039;Nobody Here&#039;", false);
});

test('a draft save to a deleted recipient keeps the body and drops the recipient', function () {
    $sender = User::factory()->create();
    $gone = User::factory()->create();
    $gone->delete();

    $response = $this->actingAs($sender)->post('/messages', [
        'intent' => 'draft',
        'recipient_id' => $gone->id,
        'body' => 'They left, but my words stay.',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('recipientWarning');

    $draft = Message::sole();
    expect($draft->body)->toBe('They left, but my words stay.');
    expect($draft->recipient_id)->toBeNull();
});

test('a draft addressed to yourself is still saved, with an error on the recipient', function () {
    $sender = User::factory()->create();

    $response = $this->actingAs($sender)->post('/messages', [
        'intent' => 'draft',
        'recipient_id' => $sender->id,
        'body' => 'Note to self.',
    ]);

    $response->assertSessionHasErrors('recipient_id');
    expect(Message::sole()->body)->toBe('Note to self.');
});

// --- Clearing the browser's local copy ------------------------------------------------

test('successful saves tell the browser which local copies to clear', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    $response = $this->actingAs($sender)->post('/messages', ['intent' => 'draft', 'body' => 'one']);
    $draft = Message::sole();
    $response->assertSessionHas('clearLocalDraft', ['new', (string) $draft->id]);

    $this->actingAs($sender)->put("/messages/{$draft->id}", ['intent' => 'draft', 'body' => 'two'])
        ->assertSessionHas('clearLocalDraft', [(string) $draft->id]);

    $this->actingAs($sender)->put("/messages/{$draft->id}", [
        'intent' => 'send',
        'recipient_id' => $recipient->id,
        'body' => 'three',
    ])->assertSessionHas('clearLocalDraft', [(string) $draft->id]);

    $this->actingAs($sender)->post('/messages', [
        'intent' => 'send',
        'recipient_id' => $recipient->id,
        'body' => 'straight out',
    ])->assertSessionHas('clearLocalDraft', fn ($keys) => $keys[0] === 'new');
});

test('the page after a save clears the local copy', function () {
    $sender = User::factory()->create();
    $draft = Message::factory()->for($sender, 'sender')->draft()->create();

    $this->actingAs($sender)
        ->withSession(['clearLocalDraft' => ['new', (string) $draft->id]])
        ->get(route('messages.edit', $draft))
        ->assertOk()
        ->assertSee('localStorage.removeItem', false);
});

// --- Session expiry ------------------------------------------------------------------

test('an expired csrf token sends the user back with their letter intact', function () {
    Route::middleware('web')->post('/_test/expired', fn () => throw new TokenMismatchException);

    $sender = User::factory()->create();

    $response = $this->actingAs($sender)
        ->from(route('messages.create'))
        ->post('/_test/expired', [
            '_token' => 'stale',
            'intent' => 'send',
            'recipient_name' => 'Ada',
            'body' => 'Two hours of writing.',
            'enclosures' => ['https://example.com/'],
        ]);

    $response->assertRedirect(route('messages.create'));
    $response->assertSessionHasErrors('session');
    $response->assertSessionHasInput('body', 'Two hours of writing.');
    $response->assertSessionHasInput('recipient_name', 'Ada');
    $response->assertSessionHasInput('enclosures', ['https://example.com/']);
    $response->assertSessionMissing('_old_input._token');

    $this->actingAs($sender)->get(route('messages.create'))
        ->assertOk()
        ->assertSee('Two hours of writing.')
        ->assertSee('example.com', false)
        ->assertSee('Your session expired');
});

test('an expired csrf token on a json request still gets a 419', function () {
    Route::middleware('web')->post('/_test/expired-json', fn () => throw new TokenMismatchException);

    $this->postJson('/_test/expired-json')->assertStatus(419);
});

test('the csrf token endpoint returns a token', function () {
    $this->get('/csrf-token')
        ->assertOk()
        ->assertJsonStructure(['token']);
});

// --- Background autosave ---------------------------------------------------------------

test('the first autosave of a new letter creates a draft and says where it lives', function () {
    $sender = User::factory()->create();

    $response = $this->actingAs($sender)->postJson(route('messages.autosave.store'), [
        'body' => 'Typed for two hours.',
    ]);

    $draft = Message::sole();

    $response->assertOk()->assertJson([
        'saved' => true,
        'id' => $draft->id,
        'update_url' => route('messages.update', $draft),
        'autosave_url' => route('messages.autosave.update', $draft),
        'edit_url' => route('messages.edit', $draft),
    ]);
    expect($draft->is_draft)->toBeTrue();
    expect($draft->sender_id)->toBe($sender->id);
    expect($draft->body)->toBe('Typed for two hours.');
});

test('autosave does not create a draft for a letter with no text', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    $this->actingAs($sender)->postJson(route('messages.autosave.store'), [
        'body' => '   ',
        'recipient_id' => $recipient->id,
    ])->assertOk()->assertJson(['saved' => false]);

    expect(Message::count())->toBe(0);
});

test('autosave updates an existing draft and is lenient about half-finished fields', function () {
    $sender = User::factory()->create();
    $draft = Message::factory()->for($sender, 'sender')->draft()->create(['recipient_id' => null, 'body' => 'old']);

    $this->actingAs($sender)->putJson(route('messages.autosave.update', $draft), [
        'body' => 'newer',
        'recipient_name' => 'Nobody Matches',
        'enclosures' => ['https://example.com/ok', 'htt', ''],
    ])->assertOk()->assertJson(['saved' => true, 'id' => $draft->id]);

    $draft->refresh();
    expect(Message::count())->toBe(1);
    expect($draft->is_draft)->toBeTrue();
    expect($draft->body)->toBe('newer');
    expect($draft->recipient_id)->toBeNull();
    expect($draft->enclosures)->toBe(['https://example.com/ok']);
});

test('autosave resolves a typed name and drops yourself or a deleted member', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create(['name' => 'Ada Lovelace']);
    $gone = User::factory()->create();
    $gone->delete();
    $draft = Message::factory()->for($sender, 'sender')->draft()->create(['recipient_id' => null]);

    $this->actingAs($sender)->putJson(route('messages.autosave.update', $draft), [
        'body' => 'hi', 'recipient_name' => 'ada lovelace',
    ])->assertOk();
    expect($draft->fresh()->recipient_id)->toBe($recipient->id);

    foreach ([$sender->id, $gone->id] as $id) {
        $this->actingAs($sender)->putJson(route('messages.autosave.update', $draft), [
            'body' => 'hi', 'recipient_id' => $id,
        ])->assertOk();
        expect($draft->fresh()->recipient_id)->toBeNull();
    }
});

test('autosave never touches someone elses draft or a sealed letter', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $recipient = User::factory()->create();
    $draft = Message::factory()->for($owner, 'sender')->draft()->create(['body' => 'private']);
    $sealed = Message::factory()->for($owner, 'sender')->for($recipient, 'recipient')->create(['body' => 'final']);

    $this->actingAs($intruder)->putJson(route('messages.autosave.update', $draft), ['body' => 'hijacked'])->assertNotFound();
    $this->actingAs($owner)->putJson(route('messages.autosave.update', $sealed), ['body' => 'changed'])->assertNotFound();

    expect($draft->fresh()->body)->toBe('private');
    expect($sealed->fresh()->body)->toBe('final');
    expect($sealed->fresh()->is_draft)->toBeFalse();
});

test('autosave rejects a body over the length limit without changing the draft', function () {
    $sender = User::factory()->create();
    $draft = Message::factory()->for($sender, 'sender')->draft()->create(['body' => 'kept']);

    $this->actingAs($sender)->putJson(route('messages.autosave.update', $draft), [
        'body' => str_repeat('a', config('pennypost.max_letter_length') + 1),
    ])->assertUnprocessable()->assertJsonValidationErrors('body');

    expect($draft->fresh()->body)->toBe('kept');
});

test('autosave requires being logged in', function () {
    $this->postJson(route('messages.autosave.store'), ['body' => 'hi'])->assertUnauthorized();

    expect(Message::count())->toBe(0);
});
