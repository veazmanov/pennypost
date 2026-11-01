<x-app-layout>
    <x-slot name="header">
        <h2 class="pp-serif font-semibold text-xl" style="color: var(--ink);">
            {{ __('Settings') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 pp-letter-card">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 pp-letter-card">
                <div class="max-w-xl">
                    <h2 class="text-lg pp-serif font-medium" style="color: var(--ink);">
                        {{ __('Download your data') }}
                    </h2>
                    <p class="mt-1 text-sm" style="color: var(--ink-soft);">
                        {{ __('Get a copy of your account information and every letter you\'ve sent, received, or drafted, in a portable JSON file.') }}
                    </p>
                    <a href="{{ route('profile.export') }}" class="pp-btn pp-btn-ghost mt-4 inline-flex">
                        {{ __('Download my data') }}
                    </a>
                </div>
            </div>

            <div class="p-4 sm:p-8 pp-letter-card">
                <div class="max-w-xl">
                    <h2 class="text-lg pp-serif font-medium" style="color: var(--ink);">
                        {{ __('Download as PDF') }}
                    </h2>
                    <p class="mt-1 text-sm" style="color: var(--ink-soft);">
                        {{ __('Get your correspondence as a printable PDF, formatted like the letters themselves.') }}
                    </p>
                    <button type="button" disabled title="{{ __('Coming soon!') }}" class="pp-btn pp-btn-ghost mt-4 inline-flex">
                        {{ __('Download as PDF') }}
                    </button>
                </div>
            </div>

            <div class="p-4 sm:p-8 pp-letter-card">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>

    </div>

</x-app-layout>
