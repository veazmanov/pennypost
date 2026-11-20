<?php

use App\Models\Message;
use App\Models\User;

test('a thread with an unread letter gets a bold, starred count on My correspondence', function () {
    $user = User::factory()->create();
    $other = User::factory()->create(['name' => 'Ada Lovelace']);
    Message::factory()->delivered()->for($other, 'sender')->for($user, 'recipient')->create();

    $this->actingAs($user)->get(route('correspondence.index'))
        ->assertOk()
        ->assertSee('font-bold', false)
        ->assertSeeText('(1)*');
});

test('opening a thread marks the letters to you as read', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $toMe = Message::factory()->delivered()->for($other, 'sender')->for($user, 'recipient')->create();
    $fromMe = Message::factory()->delivered()->for($user, 'sender')->for($other, 'recipient')->create();

    $this->actingAs($user)->get(route('correspondence.show', $other))->assertOk();

    expect($toMe->fresh()->read_at)->not->toBeNull()
        ->and($fromMe->fresh()->read_at)->toBeNull();

    $this->actingAs($user)->get(route('correspondence.index'))
        ->assertOk()
        ->assertDontSee('font-bold', false);
});

test('your own letters never count as unread to you', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Message::factory()->delivered()->for($user, 'sender')->for($other, 'recipient')->create();

    $this->actingAs($user)->get(route('correspondence.index'))
        ->assertOk()
        ->assertDontSee('font-bold', false);
});

test('letters still in the post are not unread and are not marked read', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $inThePost = Message::factory()->for($other, 'sender')->for($user, 'recipient')->create();

    $this->actingAs($user)->get(route('correspondence.show', $other))->assertOk();

    expect($inThePost->fresh()->read_at)->toBeNull();
});
