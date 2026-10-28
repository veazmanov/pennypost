<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

class LogSentMail
{
    /**
     * SMTP doesn't put anything in the Fastmail Sent folder, so this log is
     * the only record that an email left the app. Deliberately leaves out
     * the body and the SMTP transcript: both contain the login link.
     */
    public function handle(MessageSent $event): void
    {
        $message = $event->message;

        Log::channel('mail')->info('sent', [
            'to' => array_map(fn (Address $address) => $address->getAddress(), $message->getTo()),
            'subject' => $message->getSubject(),
            // Over SMTP this is the server's "queued as" ID, which is what
            // Fastmail support needs to trace a message.
            'smtp_queue_id' => $event->sent->getMessageId(),
        ]);
    }
}
