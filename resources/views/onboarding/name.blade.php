<x-guest-layout>
    <p class="text-sm mb-4" style="color: var(--ink-soft);">
        {{ __("What should we call you? Your letters will be signed and addressed to this name.") }}
    </p>

    <form method="POST" action="{{ route('onboarding.name.update') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end pt-2">
            <x-primary-button>
                {{ __('Continue') }}
            </x-primary-button>
        </div>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-sm mt-6" style="color: var(--ink-soft);">
        @csrf
        {{ __('Signed in as :email.', ['email' => Auth::user()->email]) }}
        {{ __('Not you?') }}
        <button type="submit" class="pp-link">{{ __('Log out') }}</button>
    </form>
</x-guest-layout>