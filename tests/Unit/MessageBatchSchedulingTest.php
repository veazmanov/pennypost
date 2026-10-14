<?php

use App\Models\Message;
use Carbon\CarbonImmutable;

// 2026-08-10 is a Monday. Deliveries are Mondays and Thursdays at 12:00 UTC;
// each delivery day's midnight is the cutoff for the next delivery.

test('a letter goes out in the right delivery', function (string $sealedAt, string $delivery) {
    $batch = Message::nextBatchFor(CarbonImmutable::parse($sealedAt, 'UTC'));

    expect($batch->toDateTimeString())->toBe($delivery);
})->with([
    'sunday night, just before the monday cutoff' => ['2026-08-09 23:59:59', '2026-08-13 12:00:00'],
    'exactly at monday midnight misses thursday' => ['2026-08-10 00:00:00', '2026-08-17 12:00:00'],
    'monday morning, before that day\'s delivery' => ['2026-08-10 09:00:00', '2026-08-17 12:00:00'],
    'wednesday night, just before the thursday cutoff' => ['2026-08-12 23:59:59', '2026-08-17 12:00:00'],
    'exactly at thursday midnight misses monday' => ['2026-08-13 00:00:00', '2026-08-20 12:00:00'],
    'saturday' => ['2026-08-15 10:00:00', '2026-08-20 12:00:00'],
]);

test('the cutoff for a delivery is midnight at the start of the delivery day before it', function () {
    expect(Message::cutoffFor(CarbonImmutable::parse('2026-08-13 12:00:00', 'UTC'))->toDateTimeString())
        ->toBe('2026-08-10 00:00:00')
        ->and(Message::cutoffFor(CarbonImmutable::parse('2026-08-17 12:00:00', 'UTC'))->toDateTimeString())
        ->toBe('2026-08-13 00:00:00');
});

test('the next delivery is today until the delivery time has passed', function () {
    expect(Message::nextDeliveryAt(CarbonImmutable::parse('2026-08-13 11:59:00', 'UTC'))->toDateTimeString())
        ->toBe('2026-08-13 12:00:00')
        ->and(Message::nextDeliveryAt(CarbonImmutable::parse('2026-08-13 12:01:00', 'UTC'))->toDateTimeString())
        ->toBe('2026-08-17 12:00:00');
});

test('the schedule follows the configured delivery days', function () {
    config(['pennypost.delivery_days' => [CarbonImmutable::MONDAY, CarbonImmutable::WEDNESDAY, CarbonImmutable::FRIDAY]]);

    // Sealed Tuesday: the Wednesday cutoff is next, so it arrives Friday.
    expect(Message::nextBatchFor(CarbonImmutable::parse('2026-08-11 10:00:00', 'UTC'))->toDateTimeString())
        ->toBe('2026-08-14 12:00:00')
        ->and(Message::deliveryDayNames())->toBe('Mondays, Wednesdays and Fridays');
});

test('the schedule is described in words', function () {
    expect(Message::scheduleDescription())->toBe(
        'Letters are delivered on Mondays and Thursdays. Seal yours before Monday and it arrives on Thursday; seal it before Thursday and it arrives on Monday.'
    );
});
