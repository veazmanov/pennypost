<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Message extends Model
{
    use HasFactory;


    protected $fillable = [
        'sender_id',
        'recipient_id',
        'body',
        'enclosures',
        'is_draft',
        'scheduled_for',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'is_draft' => 'boolean',
            'scheduled_for' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'enclosures' => 'array',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id')->withTrashed();
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id')->withTrashed();
    }

    public function isDelivered(): bool
    {
        return $this->delivered_at !== null;
    }


    public function scopeDelivered(Builder $query): Builder
    {
        return $query->whereNotNull('delivered_at');
    }

    public function scopeUndelivered(Builder $query): Builder
    {
        return $query->whereNull('delivered_at');
    }

    public function scopeDrafts(Builder $query): Builder
    {
        return $query->where('is_draft', true);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('is_draft', false);
    }

    public function scopeDue(Builder $query, ?CarbonInterface $asOf = null): Builder
    {
        return $query->sent()->undelivered()->where('scheduled_for', '<=', $asOf ?? now());
    }

    /**
     * Sealed letters this user is allowed to see: every delivered letter
     * they sent or received, plus their own letters still in the post.
     * Someone else's letter to them stays hidden until post day.
     */
    public function scopeVisibleTo(Builder $query, User|int $user): Builder
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $query->sent()
            ->where(function ($query) use ($userId) {
                $query->where('sender_id', $userId)->orWhere('recipient_id', $userId);
            })
            ->where(function ($query) use ($userId) {
                $query->whereNotNull('delivered_at')->orWhere('sender_id', $userId);
            });
    }

    /**
     * Letters in either direction between two people.
     */
    public function scopeBetween(Builder $query, User|int $a, User|int $b): Builder
    {
        $a = $a instanceof User ? $a->id : $a;
        $b = $b instanceof User ? $b->id : $b;

        return $query->where(function ($query) use ($a, $b) {
            $query->where(function ($q) use ($a, $b) {
                $q->where('sender_id', $a)->where('recipient_id', $b);
            })->orWhere(function ($q) use ($a, $b) {
                $q->where('sender_id', $b)->where('recipient_id', $a);
            });
        });
    }

    /**
     * Which delivery a letter sealed at $sentAt goes out in: the first
     * delivery whose cutoff it beats. Each delivery day is the cutoff for
     * the next delivery, so with Monday and Thursday a letter sealed before
     * Monday (by Sunday night) arrives Thursday, and one sealed before
     * Thursday arrives Monday.
     */
    public static function nextBatchFor(?CarbonInterface $sentAt = null): CarbonImmutable
    {
        $sentAt = CarbonImmutable::instance($sentAt ?? now())->setTimezone('UTC');
        $probe = $sentAt->startOfDay();

        for ($i = 0; $i < 21; $i++) {
            $slot = self::deliveryOn($probe);

            if ($slot && $sentAt->lessThan(self::cutoffFor($slot))) {
                return $slot;
            }

            $probe = $probe->addDay();
        }

        throw new LogicException('No delivery days are configured.');
    }

    /**
     * The moment a letter must be sealed before to go out in $batch:
     * midnight UTC at the start of the delivery day before it.
     */
    public static function cutoffFor(CarbonInterface $batch): CarbonImmutable
    {
        $probe = CarbonImmutable::instance($batch)->setTimezone('UTC')->startOfDay();

        for ($i = 0; $i < 7; $i++) {
            $probe = $probe->subDay();

            if (self::deliveryOn($probe)) {
                return $probe;
            }
        }

        throw new LogicException('No delivery days are configured.');
    }

    /**
     * The delivery moment on $date, or null if $date isn't a delivery day.
     */
    protected static function deliveryOn(CarbonImmutable $date): ?CarbonImmutable
    {
        if (! in_array($date->dayOfWeek, config('pennypost.delivery_days'), true)) {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', config('pennypost.delivery_time')));

        return $date->setTime($hour, $minute);
    }

    /**
     * The schedule in words, built from config — e.g. "Letters are
     * delivered on Mondays and Thursdays. Seal yours before Monday and it
     * arrives on Thursday; seal it before Thursday and it arrives on Monday."
     */
    public static function scheduleDescription(): string
    {
        $days = collect(config('pennypost.delivery_days'))->sort()->values();
        $name = fn (int $day) => CarbonImmutable::now()->next($day)->format('l');

        $routes = $days->map(fn (int $day, int $i) => [
            'before' => $name($day),
            'arrives' => $name($days[($i + 1) % $days->count()]),
        ]);

        $first = $routes->shift();

        return implode(' ', [
            __('Letters are delivered on :days.', ['days' => self::deliveryDayNames()]),
            collect([__('Seal yours before :before and it arrives on :arrives', $first)])
                ->concat($routes->map(fn ($route) => __('seal it before :before and it arrives on :arrives', $route)))
                ->implode('; ').'.',
        ]);
    }

    /**
     * "Mondays and Thursdays", "Mondays, Wednesdays and Fridays", ...
     */
    public static function deliveryDayNames(): string
    {
        $names = collect(config('pennypost.delivery_days'))->sort()
            ->map(fn (int $day) => CarbonImmutable::now()->next($day)->format('l').'s');

        return $names->count() > 1
            ? $names->slice(0, -1)->implode(', ').' '.__('and').' '.$names->last()
            : $names->first();
    }

    public static function humanDayLabel(CarbonInterface $date): string
    {
        $date = CarbonImmutable::instance($date)->startOfDay();
        $today = CarbonImmutable::now('UTC')->startOfDay();

        return match (true) {
            $date->equalTo($today) => __('today'),
            $date->equalTo($today->addDay()) => __('tomorrow'),
            default => $date->format('D, j M'),
        };
    }

    /**
     * The next delivery event, full stop — regardless of whether a new
     * letter could still catch it. Distinct from nextBatchFor(): on a
     * delivery day before the delivery time, today's delivery (built from
     * letters sealed before the last cutoff) hasn't gone out yet, so
     * "next delivery" is today.
     */
    public static function nextDeliveryAt(?CarbonInterface $asOf = null): CarbonImmutable
    {
        $asOf = CarbonImmutable::instance($asOf ?? now())->setTimezone('UTC');
        $probe = $asOf->startOfDay();

        for ($i = 0; $i < 8; $i++) {
            $slot = self::deliveryOn($probe);

            if ($slot && $asOf->lessThanOrEqualTo($slot)) {
                return $slot;
            }

            $probe = $probe->addDay();
        }

        throw new LogicException('No delivery days are configured.');
    }

    public function hasEnclosures(): bool
    {
        return ! empty($this->enclosures);
    }





}
