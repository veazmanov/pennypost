<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <title>{{ __('About') }} — {{ config('app.name', 'Penny Post') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link
        href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|fraunces:400,500,600,600i|space-mono:400,700"
        rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    @php
        $schedule = \App\Models\Message::scheduleDescription();
    @endphp
    <div class="pp-wrap">
        <nav class="pp-nav">
            <a href="{{ url('/') }}" class="pp-brand">
                <span class="pp-brand-mark">P</span>
                <span class="pp-brand-name pp-serif">Penny Post</span>
            </a>
            <div class="pp-navlinks">
                @auth
                    <a href="{{ route('correspondence.index') }}"
                        class="pp-btn pp-btn-solid">{{ __('My correspondence') }}</a>
                @else
                    <a href="{{ route('login') }}" class="pp-btn pp-btn-ghost">{{ __('Log in') }}</a>
                    <a href="{{ route('register') }}" class="pp-btn pp-btn-solid">{{ __('Sign up') }}</a>
                @endauth
            </div>
        </nav>

        <main>
            <section class="pp-section" style="border-top: none; padding-top: 24px;">
                <p class="pp-eyebrow">{{ __('About Penny Post') }}</p>
                <p class="pp-lede">
                    {{ __("Penny Post is a digital letter-writing platform. It exists to revive something we've quietly lost: the pleasure of writing a proper letter, and the particular anticipation of waiting for one to arrive.") }}
                </p>
                <p class="pp-lede" style="margin-top: 28px; margin-bottom: 0;">
                    {{ $schedule }}
                    {{ __('Delivery happens at a fixed moment in GMT — see the clock in your Settings page for exactly when that lands in your own time zone.') }}
                </p>
                <p class="pp-mono text-sm mt-7" style="color: var(--ink-soft);">
                    {{ trans_choice(':count letter delivered|:count letters delivered', $deliveredCount, ['count' => number_format($deliveredCount)]) }}
                    <span aria-hidden="true" class="mx-2">|</span>
                    {{ trans_choice(':count in the post|:count in the post', $inTransitCount, ['count' => number_format($inTransitCount)]) }}
                    <span aria-hidden="true" class="mx-2">|</span>
                    {{ trans_choice(':count member|:count members', $memberCount, ['count' => number_format($memberCount)]) }}
                </p>
            </section>

            <section class="pp-section" id="how-it-works">
                <div class="pp-section-head">
                    <p class="pp-kicker">{{ __('How it works') }}</p>
                    <h2 class="pp-h2 pp-serif">{{ __('From draft to doorstep in three stops') }}</h2>
                </div>
                <div class="pp-steps">
                    <div class="pp-step">
                        <div class="pp-step-dot pp-mono">1</div>
                        <h3 class="pp-step-title">{{ __('Draft') }}</h3>
                        <p class="pp-step-body">{{ __('Write to someone whenever the moment strikes.') }}</p>
                    </div>
                    <div class="pp-step">
                        <div class="pp-step-dot pp-mono">2</div>
                        <h3 class="pp-step-title">{{ __('Postmarked') }}</h3>
                        <p class="pp-step-body">{{ __('Seal it, and it waits in the post room until post day.') }}
                        </p>
                    </div>
                    <div class="pp-step">
                        <div class="pp-step-dot pp-mono">3</div>
                        <h3 class="pp-step-title">{{ __('Delivered') }}</h3>
                        <p class="pp-step-body">
                            {{ $schedule }}
                        </p>
                    </div>
                </div>

            </section>


            <section class="pp-section">
                <div class="pp-section-head">
                    <p class="pp-kicker">{{ __('Questions people ask') }}</p>
                    <h2 class="pp-h2 pp-serif">{{ __('A few things worth knowing') }}</h2>
                </div>

                <div style="display: flex; flex-direction: column; gap: 28px; max-width: 60ch;">
                    <div>
                        <h3 class="pp-stamp-title">{{ __('Is Penny Post open source?') }}</h3>
                        <p class="pp-stamp-body">
                            {{ __('Yes — the full source is public on') }}
                            <a href="https://github.com/victorelgersma/pennypost" class="pp-link" target="_blank"
                                rel="noopener">GitHub</a>.
                        </p>
                    </div>

                    <div>
                        <h3 class="pp-stamp-title">{{ __('Is Penny Post end-to-end encrypted?') }}</h3>
                        <p class="pp-stamp-body">
                            {{ __("Not yet. We looked into it seriously — real end-to-end encryption for a two-person exchange needs each letter encrypted separately to both the sender's and recipient's own keys, plus a proper key-recovery story if someone loses a device. Doing that well, on top of the Signal protocol, is honestly above my (Victor Elgersma-Azmanov's) pay grade right now. If you know someone who'd want to help implement it for a correspondence app, I'd love an introduction — reach out at") }}
                            <a href="mailto:pennypost@vjbe.net" class="pp-link">pennypost@vjbe.net</a>.
                            {{ __("In the meantime, letters are stored securely, but Penny Post can technically access message content — the same as most email and messaging services, including Google and Facebook. If you need real E2E encrypted email today, take a look at") }}
                            <a href="https://tuta.com" class="pp-link" target="_blank" rel="noopener">Tuta</a>.
                        </p>
                    </div>

                    <div>
                        <h3 class="pp-stamp-title">{{ __('Who created Penny Post?') }}</h3>
                        <p class="pp-stamp-body">
                            {{ __('Penny Post was founded by two friends who were frustrated with the aesthetics of modern messaging platforms. It is actively maintained by') }}
                            <a href="https://vjbe.net" class="pp-link" target="_blank" rel="noopener">Victor
                                Elgersma-Azmanov</a> {{ __('His co-founder prefers to stay anonymous.') }}
                        </p>
                    </div>
                    <div>
                        <h3 class="pp-stamp-title">{{ __('What time do I get my mail?') }}</h3>
                        <p class="pp-stamp-body">
                            {{ __('Messages are delivered on :days, at a fixed moment in GMT. Because GMT doesn\'t observe British Summer Time, that moment lands at a different local London time depending on the season. We\'ve put a live GMT clock and countdown on the Settings page so you can see exactly when your letters will land, wherever you are.', ['days' => \App\Models\Message::deliveryDayNames()]) }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="pp-section">
                <div class="pp-section-head">
                    <p class="pp-kicker">{{ __('Get in touch') }}</p>
                    <h2 class="pp-h2 pp-serif">{{ __('Questions, bugs, or feedback') }}</h2>
                </div>
                <p class="pp-lede" style="margin-bottom: 0;">
                    {{ __('Email') }}
                    <a href="mailto:pennypost@vjbe.net" class="pp-link">pennypost@vjbe.net</a>
                    {{ __('or raise an issue on') }}
                    <a href="https://github.com/victorelgersma/pennypost" class="pp-link" target="_blank"
                        rel="noopener">GitHub</a>.
                </p>
            </section>
        </main>

        <x-site-footer />
    </div>
</body>

</html>
