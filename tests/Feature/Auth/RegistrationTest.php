<?php

use App\Models\User;
use App\Notifications\LoginLinkNotification;
use Illuminate\Support\Facades\Notification;

test('registration screen can be rendered', function () {
    $this->get('/register')->assertStatus(200);
});

test('a new user can register with just an email and receives a login link', function () {
    Notification::fake();

    $response = $this->post('/register', ['email' => 'newcomer@example.com']);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status', 'login-link-sent');

    $user = User::where('email', 'newcomer@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBeNull();
    expect($user->email_verified_at)->toBeNull();

    Notification::assertSentTo($user, LoginLinkNotification::class);
});

test('registering with an email already in use resends a login link rather than erroring', function () {
    Notification::fake();
    $existing = User::factory()->create();

    $response = $this->post('/register', ['email' => $existing->email]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHasNoErrors();
    $this->assertDatabaseCount('users', 1);
});
test('a new user who asks again for their link gets another one', function () {
    Notification::fake();

    $this->post('/register', ['email' => 'newcomer@example.com']);
    $this->post('/register', ['email' => 'newcomer@example.com'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'login-link-sent');

    $user = User::where('email', 'newcomer@example.com')->first();
    Notification::assertSentToTimes($user, LoginLinkNotification::class, 2);
});

test('a new user whose first link failed to send can retry straight away', function () {
    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP down'));
    $this->post('/register', ['email' => 'unlucky@example.com'])->assertServerError();

    Notification::fake();
    $this->post('/register', ['email' => 'unlucky@example.com'])->assertSessionHasNoErrors();

    $user = User::where('email', 'unlucky@example.com')->first();
    Notification::assertSentTo($user, LoginLinkNotification::class);
});

test('too many links to an unverified address says so instead of pretending to send', function () {
    Notification::fake();

    foreach (range(1, 3) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.$i"])
            ->post('/register', ['email' => 'newcomer@example.com']);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
        ->post('/register', ['email' => 'newcomer@example.com'])
        ->assertSessionHasErrors('email');

    $user = User::where('email', 'newcomer@example.com')->first();
    Notification::assertSentToTimes($user, LoginLinkNotification::class, 3);
});
