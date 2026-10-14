<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;

class RescheduleMessages extends Command
{
    protected $signature = 'messages:reschedule {--dry-run : Show what would change without saving}';

    protected $description = 'Recalculate delivery dates for letters in the post after the schedule changes';

    /**
     * Each letter in the post moves to the delivery the current schedule
     * gives it, but only if that's earlier than the date it was promised
     * when it was sealed — a letter never arrives later than promised.
     * Taking the earlier of the two also means no letter overtakes one
     * sealed before it.
     */
    public function handle(): int
    {
        $changes = [];

        Message::query()->sent()->undelivered()->whereNotNull('sent_at')->orderBy('sent_at')
            ->each(function (Message $message) use (&$changes) {
                $rescheduled = Message::nextBatchFor($message->sent_at);

                if ($rescheduled->greaterThanOrEqualTo($message->scheduled_for)) {
                    return;
                }

                $changes[] = [
                    $message->id,
                    $message->sent_at->format('D j M H:i'),
                    $message->scheduled_for->format('D j M H:i'),
                    $rescheduled->format('D j M H:i'),
                ];

                if (! $this->option('dry-run')) {
                    $message->update(['scheduled_for' => $rescheduled]);
                }
            });

        if ($changes === []) {
            $this->info('No letters in the post need rescheduling.');

            return self::SUCCESS;
        }

        $this->table(['Letter', 'Sealed', 'Was due', 'Now due'], $changes);
        $this->info(($this->option('dry-run') ? 'Would reschedule ' : 'Rescheduled ').count($changes).' letter(s).');

        return self::SUCCESS;
    }
}
