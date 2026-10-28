<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <title>{{ __('Feedback') }} — {{ config('app.name', 'Penny Post') }}</title>

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
                <p class="pp-eyebrow">{{ __('Feedback') }}</p>
                <h1 class="pp-h1 pp-serif" style="font-size: 40px;">{{ __('Tell us what you think') }}</h1>
                <p class="pp-lede">
                    {{ __("Found a bug, or have an idea for making Penny Post better? Let us know below. Name and email are both optional — but if you leave an email, we can reply.") }}
                </p>

                @if (session('status') === 'feedback-sent')
                    <div class="pp-stamp-badge text-sm mb-6">
                        {{ __('Thanks — your feedback has been sent.') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('feedback.store') }}" class="space-y-5"
                    style="max-width: 480px;" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf

                    <div style="position:absolute;left:-9999px;" aria-hidden="true">
                        <label for="website">Leave this field empty</label>
                        <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div>
                        <x-input-label for="name" :value="__('Name (optional)')" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name"
                            :value="old('name')" autocomplete="name" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="email" :value="__('Email (optional)')" />
                        <x-text-input id="email" class="block mt-1 w-full" type="email" name="email"
                            :value="old('email')" autocomplete="email" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="message" :value="__('Your feedback')" />
                        <textarea id="message" name="message" rows="6" maxlength="5000"
                            class="pp-input mt-1 block w-full">{{ old('message') }}</textarea>
                        <x-input-error :messages="$errors->get('message')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end pt-2">
                        <x-primary-button x-bind:disabled="submitting">
                            <span x-show="!submitting" x-cloak>{{ __('Send feedback') }}</span>
                            <span x-show="submitting" x-cloak class="inline-flex items-center gap-2">
                                <span class="pp-spinner"></span>
                                {{ __('Sending…') }}
                            </span>
                        </x-primary-button>
                    </div>
                </form>
            </section>
        </main>

        <x-site-footer />
    </div>
</body>

</html>
