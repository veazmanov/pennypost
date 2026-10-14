<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;

class DeliverMessages extends Command
{
    protected $signature = 'messages:deliver';

    protected $description = 'Deliver all messages whose scheduled batch time has arrived';

    public function handle(): int
    {
        $delivered = Message::query()->due()->update(['delivered_at' => now()]);

        if ($delivered === 0) {
            $this->info('No messages are due for delivery.');
            return self::SUCCESS;
        }

        $this->info("Delivered {$delivered} message(s).");

        return self::SUCCESS;
    }
}
