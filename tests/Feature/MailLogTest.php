<?php

use App\Models\User;
use Illuminate\Support\Facades\Log;

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
