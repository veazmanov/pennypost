<?php

use App\Models\Message;
use Carbon\CarbonImmutable;

$mondayString = '2026-08-10';

test('a message sent before monday noon utc goes out the same week', function () use ($mondayString) {
    $sentAt = CarbonImmutable::parse("{$mondayString} 09:00:00", 'UTC'); // Monday

    $batch = Message::nextBatchFor($sentAt);

    expect($batch->toDateString())->toBe('2026-08-14')
        ->and($batch->format('H:i'))->toBe('12:00');
});

test('a message sent exactly at the monday noon utc cutoff still makes that batch', function ()  {
    $batch = Message::nextBatchFor(CarbonImmutable::parse('2026-08-10 12:00:00', 'UTC'));

    expect($batch->toDateString())->toBe('2026-08-14');
});

test('a message sent just after the wednesday noon utc cutoff waits an extra week', function () {
    $batch = Message::nextBatchFor(CarbonImmutable::parse('2026-08-12 12:00:01', 'UTC'));

    expect($batch->toDateString())->toBe('2026-08-21');
});

test('a message sent on saturday waits for the following weeks batch', function () {
    $batch = Message::nextBatchFor(CarbonImmutable::parse('2026-08-15 10:00:00', 'UTC'));

    expect($batch->toDateString())->toBe('2026-08-21');
});

test('a message sent friday morning misses same-day delivery', function () {
    $batch = Message::nextBatchFor(CarbonImmutable::parse('2026-08-21 09:00:00', 'UTC'));

    expect($batch->toDateString())->toBe('2026-08-28');
});

test('the cutoff for a batch is cutoff_days_before_batch days earlier, at the same time', function () {
    config(['pennypost.cutoff_days_before_batch' => 2]);

    $cutoff = Message::cutoffFor(CarbonImmutable::parse('2026-08-14 12:00:00', 'UTC'));

    expect($cutoff->toDateTimeString())->toBe('2026-08-12 12:00:00');
});
