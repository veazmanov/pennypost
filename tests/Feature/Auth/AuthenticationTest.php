<?php

use App\Models\User;
use App\Notifications\LoginLinkNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

test('login screen can be rendered', function () {
    $this->get('/login')->assertStatus(200);
});

test('requesting a login link sends a notification to an existing user', function () {
    Notification::fake();
    $user = User::factory()->create();

    $response = $this->post('/login', ['email' => $user->email]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status', 'login-link-sent');
    Notification::assertSentTo($user, LoginLinkNotification::class);
});

test('visiting a valid signed login link authenticates an existing user', function () {
    $user = User::factory()->create(); // factory gives it a name already

    $url = URL::temporarySignedRoute('login.verify', now()->addMinutes(15), ['user' => $user->id]);
    $response = $this->get($url);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('correspondence.index'));
});

test('a user with no name yet is sent to onboarding after clicking their login link', function () {
    $user = User::factory()->create(['name' => null]);

    $url = URL::temporarySignedRoute('login.verify', now()->addMinutes(15), ['user' => $user->id]);
    $response = $this->get($url);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('onboarding.name'));
});

test('an unsigned login link is rejected', function () {
    $user = User::factory()->create();

    $this->get("/login/{$user->id}")->assertForbidden();
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('a user stuck on onboarding can see their email and log out', function () {
    $user = User::factory()->create(['name' => null, 'email' => 'typo@example.com']);

    $this->actingAs($user)
        ->get(route('onboarding.name'))
        ->assertOk()
        ->assertSee('typo@example.com')
        ->assertSee(route('logout'));

    $response = $this->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('requesting a login link for a soft-deleted account shows a friendly message instead of crashing', function () {
    $user = User::factory()->create(['email' => 'ghost@example.com']);
    $user->delete(); // soft delete without going through the anonymization flow

    $response = $this->post('/login', ['email' => 'ghost@example.com']);

    $response->assertSessionHasErrors('email');
});
