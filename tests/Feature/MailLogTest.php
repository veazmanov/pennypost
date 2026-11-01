<?php

use App\Models\User;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Psr\Log\LoggerInterface;

test('every email the app sends is logged without its body', function () {
    config(['mail.default' => 'array']);
    $user = User::factory()->create(['email' => 'reader@example.com']);

    Log::shouldReceive('channel')->with('mail')->once()->andReturnSelf();
    Log::shouldReceive('info')->once()->withArgs(function ($message, $context) {
        return $message === 'sent'
            && $context['to'] === ['reader@example.com']
            && $context['subject'] === 'Your Penny Post login link'
            && array_key_exists('smtp_queue_id', $context)
            && ! str_contains(json_encode($context), 'signature=');
    });

    $this->post('/login', ['email' => $user->email]);
});

function failingMailLog(): void
{
    $mailLog = Mockery::mock(LoggerInterface::class);
    $mailLog->shouldReceive('info')->andThrow(new UnexpectedValueException('Permission denied'));

    Log::partialMock()->shouldReceive('channel')->with('mail')->andReturn($mailLog);
}

test('a mail log that cannot be written does not break logging in', function () {
    config(['mail.default' => 'array']);
    Exceptions::fake();
    failingMailLog();
    $user = User::factory()->create(['email' => 'reader@example.com']);

    $this->post('/login', ['email' => $user->email])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'login-link-sent');

    $sent = app('mailer')->getSymfonyTransport()->messages();
    expect($sent)->toHaveCount(1)
        ->and($sent->first()->getOriginalMessage()->getSubject())->toBe('Your Penny Post login link');
    Exceptions::assertReported(UnexpectedValueException::class);
});

test('a mail log that cannot be written still counts links to unverified addresses', function () {
    config(['mail.default' => 'array']);
    Exceptions::fake();
    failingMailLog();
    $user = User::factory()->unverified()->create(['email' => 'newcomer@example.com']);

    $this->post('/login', ['email' => $user->email])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'login-link-sent');

    expect(app('mailer')->getSymfonyTransport()->messages())->toHaveCount(1)
        ->and(RateLimiter::attempts('unverified-login-link:newcomer@example.com'))->toBe(1);
    Exceptions::assertReported(UnexpectedValueException::class);
});
