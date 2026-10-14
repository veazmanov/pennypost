<?php

use App\Models\Message;
use App\Models\User;

test('the about page shows delivered, in-the-post and member counts', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    User::factory()->create(['name' => null]); // hasn't finished onboarding
    User::factory()->create()->delete();        // deleted account

    Message::factory()->for($alice, 'sender')->for($bob, 'recipient')->delivered()->count(3)->create();
    Message::factory()->for($alice, 'sender')->for($bob, 'recipient')->count(2)->create();
    Message::factory()->for($alice, 'sender')->for($bob, 'recipient')->draft()->create();

    $this->get(route('about'))
        ->assertOk()
        ->assertViewHas('deliveredCount', 3)
        ->assertViewHas('inTransitCount', 2)
        ->assertViewHas('memberCount', 2)
        ->assertSee('3 letters delivered')
        ->assertSee('2 in the post')
        ->assertSee('2 members');
});
