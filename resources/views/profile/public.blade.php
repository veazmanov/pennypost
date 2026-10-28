<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <title>{{ $profile->name }} — {{ config('app.name', 'Penny Post') }}</title>

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
                <p class="pp-eyebrow">{{ __('Penny Post profile') }}</p>
                <h1 class="pp-h1 pp-serif" style="font-size: 40px;">{{ $profile->name }}</h1>
                <p class="pp-lede">
                    {{ __('Member since :date.', ['date' => $profile->created_at->format('F Y')]) }}
                </p>

                <a href="{{ route('messages.create', ['to_id' => $profile->id, 'to_name' => $profile->name]) }}"
                    class="pp-btn pp-btn-solid">
                    {{ __('Write to :name', ['name' => $profile->name]) }}
                    <x-icons.pen />
                </a>
            </section>
        </main>

        <x-site-footer />
    </div>
</body>

</html>
