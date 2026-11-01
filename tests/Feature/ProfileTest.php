<?php

use App\Models\User;
use App\Models\Message;
use App\Notifications\AccountDeletionNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('the profile page no longer shows the pickup and delivery card', function () {
    $this->actingAs(User::factory()->create())->get('/profile')
        ->assertOk()
        ->assertDontSee('Pickup and delivery')
        ->assertDontSee('Next pickup');
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('requesting account deletion sends a confirmation link rather than deleting immediately', function () {
    Notification::fake();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->delete('/profile');

    $response->assertRedirect(route('profile.edit'));
    $response->assertSessionHas('status', 'account-deletion-requested');
    Notification::assertSentTo($user, AccountDeletionNotification::class);
    $this->assertNotNull($user->fresh());
});

test('visiting the signed deletion link permanently deletes the account and logs out the session', function () {
    $user = User::factory()->create();
    $url = URL::temporarySignedRoute('profile.destroy.confirm', now()->addMinutes(15), ['user' => $user->id]);

    $response = $this->actingAs($user)->get($url);

    $response->assertRedirect('/');
    $response->assertSessionHas('status', 'account-deleted');
    $this->assertGuest();
    $this->assertNull(User::find($user->id));
});

test('an unsigned deletion link is rejected and the account is kept', function () {
    $user = User::factory()->create();

    $this->get("/profile/delete/{$user->id}")->assertForbidden();
    $this->assertNotNull($user->fresh());
});

test('a user can export their data, including drafts, sent letters, and only delivered received letters', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Message::factory()->for($user, 'sender')->for($other, 'recipient')->create(['body' => 'A sent letter.']);
    Message::factory()->for($user, 'sender')->draft()->create(['body' => 'A draft.']);
    Message::factory()->for($other, 'sender')->for($user, 'recipient')->delivered()->create(['body' => 'A delivered letter to me.']);
    Message::factory()->for($other, 'sender')->for($user, 'recipient')->create(['body' => 'Still sealed, not mine to see yet.']);

    $response = $this->actingAs($user)->get('/profile/export');

    $response->assertOk();
    $content = $response->streamedContent();

    expect($content)->toContain('A sent letter.')
        ->toContain('A draft.')
        ->toContain('A delivered letter to me.')
        ->not->toContain('Still sealed, not mine to see yet.');
});