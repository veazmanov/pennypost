<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <title>{{ __('Privacy') }} — {{ config('app.name', 'Penny Post') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link
        href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|space-mono:400,700"
        rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
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
                <p class="pp-eyebrow">{{ __('Privacy') }}</p>
                <h1 class="pp-h1 pp-serif" style="font-size: 40px;">{{ __('What Penny Post does with your data') }}</h1>
                <p class="pp-lede">
                    {{ __('Penny Post is a small, personally-run project. This page explains, plainly, what data is collected, why, and what your rights are — including under the EU General Data Protection Regulation (GDPR).') }}
                </p>
            </section>

            <section class="pp-section">
                <div class="pp-section-head">
                    <p class="pp-kicker">{{ __('What we collect') }}</p>
                    <h2 class="pp-h2 pp-serif">{{ __('Just the essentials') }}</h2>
                </div>
                <div style="display: flex; flex-direction: column; gap: 24px; max-width: 60ch;">
                    <div>
                        <h3 class="pp-stamp-title">{{ __('Account information') }}</h3>
                        <p class="pp-stamp-body">
                            {{ __('Your email address, and the name you choose to sign your letters with. That\'s the entire account — no phone number, no address, no payment details.') }}
                        </p>
                    </div>
                    <div>
                        <h3 class="pp-stamp-title">{{ __('Letters and drafts') }}</h3>
                        <p class="pp-stamp-body">
                            {{ __('The content of letters you send and receive, and any drafts you\'ve started. Drafts are only visible to you. Sent letters are visible to you and the person you wrote to.') }}
                        </p>
                    </div>
                    <div>
                        <h3 class="pp-stamp-title">{{ __('Session and security data') }}</h3>
                        <p class="pp-stamp-body">
                            {{ __('When you log in, we briefly store your IP address and browser identifier to keep your session working and to prevent abuse of the login system (for example, limiting how many login links can be requested in a short period). This isn\'t used for tracking or profiling.') }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="pp-section">
                <div class="pp-section-head">
                    <p class="pp-kicker">{{ __('Why we collect it') }}</p>
                    <h2 class="pp-h2 pp-serif">{{ __('Legal basis for processing') }}</h2>
                </div>
                <div style="display: flex; flex-direction: column; gap: 24px; max-width: 60ch;">
                    <div>
                        <h3 class="pp-stamp-title">{{ __('To provide the service') }}</h3>
                        <p class="pp-stamp-body">
                            {{ __('Your email, name, and letters are processed because they\'re necessary to provide the correspondence service you\'ve signed up for (Article 6(1)(b) GDPR — performance of a contract).') }}
                        </p>
                    </div>
                    <div>
                        <h3 class="pp-stamp-title">{{ __('To keep the service secure') }}</h3>
                        <p class="pp-stamp-body">
                            {{ __('IP addresses and rate-limiting data are processed under legitimate interest (Article 6(1)(f) GDPR) — specifically, preventing spam, abuse, and unauthorized account access.') }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="pp-section">
                <div class="pp-section-head">
                    <p class="pp-kicker">{{ __('Who else sees it') }}</p>
                    <h2 class="pp-h2 pp-serif">{{ __('Third parties and hosting') }}</h2>
                </div>
                <div style="display: flex; flex-direction: column; gap: 24px; max-width: 60ch;">
                    <div>
                        <h3 class="pp-stamp-title">{{ __('Hosting') }}</h3>
                        <p class="pp-stamp-body">
                            {{ __('Penny Post runs on a server hosted in the EU. Letters are stored in a database on that server — not with a third-party cloud database provider.') }}
                        </p>
                    </div>
                    <div>
                        <h3 class="pp-stamp-title">{{ __('Sending emails') }}</h3>
                        <p class="pp-stamp-body">
                            {{ __('Login links and account notifications are sent via Fastmail, an independent email provider. Fastmail processes your email address solely to deliver these messages. Penny Post has opted in to Fastmail\'s European data region, so the primary copy of this mail data is stored in Amsterdam — though, per Fastmail\'s own disclosures, resilient backups and some account metadata are still replicated to their US locations as part of their standard infrastructure. This processing is governed by a Data Processing Agreement between Penny Post and Fastmail, which you can read') }} <a href="https://www.fastmail.com/policies/dpa/" style="text-decoration: underline">here</a>.
                        </p>
                    </div>
                    <div>
                        <h3 class="pp-stamp-title">{{ __('Fonts') }}</h3>
                        <p class="pp-stamp-body">
                            {{ __('Typefaces are loaded from Bunny Fonts, a privacy-focused, EU-based font service that doesn\'t log or track visitors — unlike Google Fonts\' standard embed, which sends visitor IP addresses to Google.') }}
                        </p>
                    </div>
                    <div>
                        <h3 class="pp-stamp-title">{{ __('No advertising, no analytics, no trackers') }}</h3>
                        <p class="pp-stamp-body">
                            {{ __('Penny Post doesn\'t run ads, analytics scripts, or third-party trackers of any kind. There is nothing to opt out of, which is why we do not have a "cookie banner"') }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="pp-section">
                <div class="pp-section-head">
                    <p class="pp-kicker">{{ __('How long we keep it') }}</p>
                    <h2 class="pp-h2 pp-serif">{{ __('Retention') }}</h2>
                </div>
                <p class="pp-lede" style="margin-bottom: 0;">
                    {{ __('Your account data is kept for as long as your account is active. Drafts are deleted immediately if you delete them, or if you delete your account entirely. Sent letters remain visible to the person you sent them to even after you delete your account, since a letter you\'ve already sent is something the recipient has a right to keep — much like a paper letter someone has already received.') }}
                </p>
            </section>

            <section class="pp-section">
                <div class="pp-section-head">
                    <p class="pp-kicker">{{ __('Your rights') }}</p>
                    <h2 class="pp-h2 pp-serif">{{ __('What you can do') }}</h2>
                </div>
                <div style="display: flex; flex-direction: column; gap: 24px; max-width: 60ch;">
                    <div>
                        <h3 class="pp-stamp-title">{{ __('Access and correction') }}</h3>
                        <p class="pp-stamp-body">
                            {{ __('You can view and update your name and email at any time from your') }}
                            <a href="{{ route('profile.edit') }}" class="pp-link">{{ __('profile settings') }}</a>.
                        </p>
                    </div>
                    <div>
                        <h3 class="pp-stamp-title">{{ __('Erasure') }}</h3>
                        <p class="pp-stamp-body">
                            {{ __('You can delete your account at any time from your profile settings. Your drafts are deleted permanently, and your account is anonymized. As noted above, letters you\'ve already sent remain visible to their recipients.') }}
                        </p>
                    </div>
                    <div>
                        <h3 class="pp-stamp-title">{{ __('Portability') }}</h3>
                        <p class="pp-stamp-body">
                            {{ __('You can download a copy of your correspondence in a portable format.') }}
                        </p>
                    </div>
                    <div>
                        <h3 class="pp-stamp-title">{{ __('Questions or requests') }}</h3>
                        <p class="pp-stamp-body">
                            {{ __('For anything not covered here, or to make a request about your data, email') }}
                            <a href="mailto:pennypost@vjbe.net" class="pp-link">pennypost@vjbe.net</a>.
                        </p>
                    </div>
                </div>
            </section>

            <section class="pp-section">
                <div class="pp-section-head">
                    <p class="pp-kicker">{{ __('Last updated') }}</p>
                    <h2 class="pp-h2 pp-serif">{{ now()->format('j F Y') }}</h2>
                </div>
                <p class="pp-lede" style="margin-bottom: 0;">
                    {{ __('This page may be updated as Penny Post changes. Significant changes will be noted here.') }}
                </p>
            </section>
        </main>

        <x-site-footer />
    </div>
</body>

</html>