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
                {{-- Times are formatted in the browser so they show in the viewer's own time zone. --}}
                <div class="max-w-xl" x-data="{
                    now: '',
                    pickupAt: new Date('{{ $nextPickup->toIso8601String() }}'),
                    deliveryAt: new Date('{{ $nextBatch->toIso8601String() }}'),
                    arrivesAt: new Date('{{ $nextCatchableBatch->toIso8601String() }}'),
                    sealedBefore: new Date('{{ $nextBatchCutoff->toIso8601String() }}'),
                    time(date) {
                        return date.toLocaleString(undefined, {
                            weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
                        });
                    },
                    day(date) {
                        return date.toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'short' });
                    },
                    init() {
                        this.now = this.time(new Date());
                        setInterval(() => this.now = this.time(new Date()), 1000);
                    }
                }">
                    <h2 class="text-lg pp-serif font-medium" style="color: var(--ink);">
                        {{ __('Pickup and delivery') }}
                    </h2>
                    <p class="mt-1 text-sm" style="color: var(--ink-soft);">
                        {{ __('Your time now:') }} <span x-text="now"></span>
                    </p>

                    <div class="mt-6">
                        <h3 class="text-sm pp-serif font-medium" style="color: var(--ink);">
                            {{ __('Next pickup') }}
                        </h3>
                        <p class="pp-mono mt-1" style="color: var(--ink); font-size: 1.25rem;" x-text="time(pickupAt)"></p>
                        <p class="mt-1 text-sm" style="color: var(--ink-soft);">
                            {{ __('Seal a letter before this and it arrives on') }} <span x-text="day(arrivesAt)"></span>.
                        </p>
                    </div>

                    <div class="mt-6 pt-6" style="border-top: 1px solid var(--line);">
                        <h3 class="text-sm pp-serif font-medium" style="color: var(--ink);">
                            {{ __('Next delivery') }}
                        </h3>
                        <p class="pp-mono mt-1" style="color: var(--ink); font-size: 1.25rem;" x-text="time(deliveryAt)"></p>
                        <p class="mt-1 text-sm" style="color: var(--ink-soft);">
                            {{ __('Already in the post: letters sealed before') }} <span x-text="time(sealedBefore)"></span>.
                        </p>
                    </div>
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
