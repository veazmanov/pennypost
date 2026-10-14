<?php

use App\Models\Message;
use Carbon\CarbonImmutable;

test('rescheduling moves letters in the post earlier, never later', function () {
    // Sealed Tuesday under the old Friday schedule: promised Friday 16 Oct.
    // The new rule would say Monday 19 Oct, which is later, so it stays.
    $keepsFriday = Message::factory()->create([
        'sent_at' => CarbonImmutable::parse('2026-10-13 10:00:00', 'UTC'),
        'scheduled_for' => CarbonImmutable::parse('2026-10-16 12:00:00', 'UTC'),
    ]);

    // Sealed Wednesday evening: promised Friday 23 Oct, new rule says Monday 19 Oct.
    $movesEarlier = Message::factory()->create([
        'sent_at' => CarbonImmutable::parse('2026-10-14 21:00:00', 'UTC'),
        'scheduled_for' => CarbonImmutable::parse('2026-10-23 12:00:00', 'UTC'),
    ]);

    $delivered = Message::factory()->delivered()->create([
        'sent_at' => CarbonImmutable::parse('2026-10-01 10:00:00', 'UTC'),
        'scheduled_for' => CarbonImmutable::parse('2026-10-09 12:00:00', 'UTC'),
    ]);

    $this->artisan('messages:reschedule')->assertExitCode(0);

    expect($keepsFriday->fresh()->scheduled_for->toDateTimeString())->toBe('2026-10-16 12:00:00')
        ->and($movesEarlier->fresh()->scheduled_for->toDateTimeString())->toBe('2026-10-19 12:00:00')
        ->and($delivered->fresh()->scheduled_for->toDateTimeString())->toBe('2026-10-09 12:00:00');
});

test('a dry run changes nothing', function () {
    $letter = Message::factory()->create([
        'sent_at' => CarbonImmutable::parse('2026-10-14 21:00:00', 'UTC'),
        'scheduled_for' => CarbonImmutable::parse('2026-10-23 12:00:00', 'UTC'),
    ]);

    $this->artisan('messages:reschedule --dry-run')
        ->expectsOutputToContain('Would reschedule 1 letter(s).')
        ->assertExitCode(0);

    expect($letter->fresh()->scheduled_for->toDateTimeString())->toBe('2026-10-23 12:00:00');
});
