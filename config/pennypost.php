<?php

use Carbon\CarbonInterface;

return [

    /*
    |--------------------------------------------------------------------------
    | Letter Length
    |--------------------------------------------------------------------------
    |
    | The maximum number of characters a letter body may contain. Enforced
    | both server-side (MessageController::save()) and as the textarea's
    | maxlength attribute in messages/create.blade.php.
    |
    */

    'max_letter_length' => env('PENNYPOST_MAX_LETTER_LENGTH', 20000),


    /*
    |--------------------------------------------------------------------------
    | Delivery Schedule
    |--------------------------------------------------------------------------
    |
    | Letters are delivered on these days (Carbon day-of-week numbers) at
    | `delivery_time`, UTC. Each delivery day is also the cutoff for the
    | next delivery: a letter sealed before midnight at the start of one
    | delivery day arrives on the following one. With Monday and Thursday,
    | seal before Monday (by Sunday night) and it arrives Thursday; seal
    | before Thursday and it arrives Monday. See Message::nextBatchFor().
    |
    */

    'delivery_days' => [CarbonInterface::MONDAY, CarbonInterface::THURSDAY],

    'delivery_time' => '12:00',

    /*
    |--------------------------------------------------------------------------
    | Short Profile URL
    |--------------------------------------------------------------------------
    |
    | The short redirect domain (see the pp.vjbe.net Caddy block) that
    | bounces /{username} and /@{username} to the real public profile at
    | /u/{username}. Used only for what "Copy public profile" hands the
    | user — the "View public profile" link and the canonical page itself
    | are unaffected. If unset, copying falls back to the full URL.
    |
    */

    'short_profile_domain' => env('PENNYPOST_SHORT_PROFILE_DOMAIN'),

];
