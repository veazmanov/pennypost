<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <title>{{ config('app.name', 'Penny Post') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link
        href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|space-mono:400,700"
        rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    @guest
        <div class="pp-hero-full" x-data="{ loaded: false }" x-init="if ($refs.heroImg.complete) loaded = true">
            <img x-ref="heroImg" src="https://drive.vjbe.net/2026-09-01-a_man_writing_at_a_table_2001.109.6.jpg"
                alt="{{ __('A man writing a letter at a table') }}" decoding="async" fetchpriority="low"
                x-on:load="loaded = true" :class="{ 'pp-hero-img--loaded': loaded }" class="pp-hero-img">
            <div class="pp-hero-overlay"></div>

            @if (session('status') === 'account-deleted')
                <div class="pp-wrap" style="position: relative; z-index: 2; padding-top: 28px;">
                    <div class="pp-banner">
                        {{ __('Your account and everything in it has been deleted.') }}
                    </div>
                </div>
            @endif

            <div class="pp-hero-center">
                <div class="pp-hero-brand">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                        <span class="pp-brand-mark">P</span>
                        <span class="pp-brand-name pp-serif">Penny Post</span>
                    </div>
                    <p class="pp-hero-tagline">{{ __('The Correspondence App') }}</p>
                </div>

                <div class="pp-hero-buttons-row">
                    <a href="{{ route('login') }}" class="pp-btn pp-btn-ghost pp-btn-on-photo">{{ __('Log in') }}</a>
                    <a href="{{ route('register') }}" class="pp-btn pp-btn-solid">{{ __('Sign up') }}</a>
                    <a href="{{ route('about') }}" class="pp-btn pp-btn-ghost pp-btn-on-photo">{{ __('About') }}</a>
                </div>
            </div>
        </div>
    @endguest

    <div class="pp-wrap">
        @auth
            <nav class="pp-nav">
                <a href="{{ url('/') }}" class="pp-brand">
                    <span class="pp-brand-mark">P</span>
                    <span class="pp-brand-name pp-serif">Penny Post</span>
                </a>
                <div class="pp-navlinks">
                    <a href="{{ route('correspondence.index') }}"
                        class="pp-btn pp-btn-solid">{{ __('My correspondence') }}</a>
                </div>
            </nav>

            @if (session('status') === 'account-deleted')
                <div class="pp-banner">
                    {{ __('Your account and everything in it has been deleted.') }}
                </div>
            @endif
        @endauth

        <x-site-footer />
    </div>
</body>

</html>