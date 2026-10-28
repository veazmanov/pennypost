<?php

use App\Models\Message;
use App\Models\User;

function downloadUrl(Message $message, string $format): string
{
    return route('messages.download', [$message, $format]);
}

// --- Sealed letters ---------------------------------------------------------------------

test('the recipient can download a delivered letter as PDF and TXT', function () {
    $sender = User::factory()->create(['name' => 'Ada Lovelace']);
    $recipient = User::factory()->create(['name' => 'Charles Babbage']);
    $letter = Message::factory()->delivered()->for($sender, 'sender')->for($recipient, 'recipient')->create([
        'body' => 'Dear Charles,',
        'sent_at' => '2026-10-12 09:30:00',
    ]);

    $this->actingAs($recipient)->get(downloadUrl($letter, 'pdf'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertDownload('penny-post-2026-10-12-from-ada-lovelace.pdf');

    $this->actingAs($recipient)->get(downloadUrl($letter, 'txt'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertDownload('penny-post-2026-10-12-from-ada-lovelace.txt');
});

test('the PDF embeds ET Book', function () {
    $recipient = User::factory()->create();
    $letter = Message::factory()->delivered()->for($recipient, 'recipient')->create(['body' => 'Hello']);

    $pdf = $this->actingAs($recipient)->get(downloadUrl($letter, 'pdf'))->assertOk()->getContent();

    expect($pdf)->toStartWith('%PDF-');
    expect($pdf)->toMatch('#/BaseFont\s*/[A-Z]{6}\+ETBembo-RomanLF#');
    expect($pdf)->toContain('/FontFile2');
});

test('the recipient gets a 404 for a letter that has not been delivered yet', function () {
    $recipient = User::factory()->create();
    $letter = Message::factory()->for($recipient, 'recipient')->create();

    foreach (['pdf', 'txt'] as $format) {
        $this->actingAs($recipient)->get(downloadUrl($letter, $format))->assertNotFound();
    }
});

test('an unrelated user gets a 404 for someone elses letter', function () {
    $letter = Message::factory()->delivered()->create();
    $stranger = User::factory()->create();

    foreach (['pdf', 'txt'] as $format) {
        $this->actingAs($stranger)->get(downloadUrl($letter, $format))->assertNotFound();
    }
});

test('the sender can download their own sealed letter before it is delivered', function () {
    $sender = User::factory()->create();
    $letter = Message::factory()->for($sender, 'sender')->create();

    $this->actingAs($sender)->get(downloadUrl($letter, 'pdf'))->assertOk();
    $this->actingAs($sender)->get(downloadUrl($letter, 'txt'))->assertOk();
});

test('an unknown format is a 404', function () {
    $sender = User::factory()->create();
    $letter = Message::factory()->for($sender, 'sender')->create();

    $this->actingAs($sender)->get("/messages/{$letter->id}/download/docx")->assertNotFound();
});

test('guests are sent to log in', function () {
    $letter = Message::factory()->delivered()->create();

    $this->get(downloadUrl($letter, 'txt'))->assertRedirect(route('login'));
});

test('the TXT keeps the body exactly, with a header and enclosures', function () {
    $sender = User::factory()->create(['name' => 'Ada Lovelace']);
    $recipient = User::factory()->create(['name' => 'Charles Babbage']);
    $letter = Message::factory()->delivered()->for($sender, 'sender')->for($recipient, 'recipient')->create([
        'body' => "Dear Charles,\r\n\r\nThe engine — it works!\r\n  Indented line.\r\n\r\n\r\nYours,",
        'enclosures' => ['https://example.com/notes', 'https://example.com/diagram.png'],
        'sent_at' => '2026-10-12 09:30:00',
    ]);

    $text = $this->actingAs($recipient)->get(downloadUrl($letter, 'txt'))->assertOk()->getContent();

    expect($text)->toBe(
        "Date: 12 October 2026\n".
        "To: Charles Babbage\n".
        "From: Ada Lovelace\n".
        "\n".
        "Dear Charles,\n\nThe engine — it works!\n  Indented line.\n\n\nYours,\n".
        "\n".
        "Enclosed:\n".
        "https://example.com/notes\n".
        "https://example.com/diagram.png\n"
    );
});

test('the TXT has no Enclosed heading when there are no enclosures', function () {
    $recipient = User::factory()->create();
    $letter = Message::factory()->delivered()->for($recipient, 'recipient')->create(['body' => 'Short note.']);

    $text = $this->actingAs($recipient)->get(downloadUrl($letter, 'txt'))->getContent();

    expect($text)->toEndWith("\n\nShort note.\n");
    expect($text)->not->toContain('Enclosed:');
});

test('delivered letters in a thread show download links', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $delivered = Message::factory()->delivered()->for($other, 'sender')->for($user, 'recipient')->create();
    $inThePost = Message::factory()->for($user, 'sender')->for($other, 'recipient')->create();

    $this->actingAs($user)->get(route('correspondence.show', $other))
        ->assertOk()
        ->assertSee(downloadUrl($delivered, 'pdf'))
        ->assertSee(downloadUrl($delivered, 'txt'))
        ->assertDontSee(downloadUrl($inThePost, 'pdf'));
});

// --- Drafts -------------------------------------------------------------------------------

test('the owner can download a draft as PDF and TXT', function () {
    $owner = User::factory()->create(['name' => 'Ada Lovelace']);
    $recipient = User::factory()->create(['name' => 'Charles Babbage']);
    $this->travelTo('2026-10-20 14:00:00');
    $draft = Message::factory()->draft()->for($owner, 'sender')->for($recipient, 'recipient')->create([
        'body' => "Half a thought\nand another.",
    ]);
    $this->travelBack();

    $this->actingAs($owner)->get(downloadUrl($draft, 'pdf'))
        ->assertOk()
        ->assertDownload("penny-post-draft-2026-10-20-{$draft->id}.pdf");

    $response = $this->actingAs($owner)->get(downloadUrl($draft, 'txt'))
        ->assertOk()
        ->assertDownload("penny-post-draft-2026-10-20-{$draft->id}.txt");

    expect($response->getContent())->toBe(
        "Draft\nDate: 20 October 2026\nTo: Charles Babbage\nFrom: Ada Lovelace\n\nHalf a thought\nand another.\n"
    );
});

test('a draft with no recipient leaves out the To line', function () {
    $owner = User::factory()->create(['name' => 'Ada Lovelace']);
    $draft = Message::factory()->draft()->for($owner, 'sender')->create(['recipient_id' => null, 'body' => 'Nobody yet.']);

    $text = $this->actingAs($owner)->get(downloadUrl($draft, 'txt'))->assertOk()->getContent();
    expect($text)->not->toContain('To:');

    $this->actingAs($owner)->get(downloadUrl($draft, 'pdf'))->assertOk();
});

test('nobody but the owner can download a draft, not even its intended recipient', function () {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $draft = Message::factory()->draft()->for($owner, 'sender')->for($recipient, 'recipient')->create();

    foreach ([$recipient, User::factory()->create()] as $other) {
        foreach (['pdf', 'txt'] as $format) {
            $this->actingAs($other)->get(downloadUrl($draft, $format))->assertNotFound();
        }
    }
});

test('the drafts list and the edit page link to the draft downloads', function () {
    $owner = User::factory()->create();
    $draft = Message::factory()->draft()->for($owner, 'sender')->create();

    $this->actingAs($owner)->get(route('messages.drafts'))
        ->assertOk()
        ->assertSee(downloadUrl($draft, 'pdf'))
        ->assertSee(downloadUrl($draft, 'txt'));

    $this->actingAs($owner)->get(route('messages.edit', $draft))
        ->assertOk()
        ->assertSee(downloadUrl($draft, 'pdf'))
        ->assertSee(downloadUrl($draft, 'txt'));
});
