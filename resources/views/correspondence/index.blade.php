<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <h2 class="pp-serif font-semibold text-xl" style="color: var(--ink);">
                {{ __('My correspondence') }}
            </h2>
            <div class="flex items-center gap-3">
                @if ($draftCount > 0)
                    <a href="{{ route('messages.drafts') }}" class="pp-btn pp-btn-ghost">
                        {{ __('Drafts') }} · {{ $draftCount }}
                    </a>
                @endif
                <a href="{{ route('messages.create') }}" class="pp-btn pp-btn-solid">
                    {{ __('Write') }}
                    <x-icons.pen />
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="pp-content-wrap">
            @if ($correspondences->isEmpty())
                <div class="pp-paper-card p-6 text-sm space-y-3" style="color: var(--ink-soft);">
                    <p>{{ __("You don't have any delivered correspondence yet.") }}</p>
                </div>
            @else
                {{-- One sheet with a plain list of names, rather than a card per person. --}}
                <ul class="pp-paper-card pp-letter-bleed divide-y divide-[var(--line)] px-4 py-3 sm:px-8 sm:py-5">
                    @foreach ($correspondences as $thread)
                        <li>
                            <a href="{{ route('correspondence.show', $thread->person) }}"
                                class="pp-thread-link flex items-baseline justify-between gap-4 py-3">
                                <span class="pp-serif font-medium uppercase" style="color: var(--ink);">
                                    {{ $thread->person->name }}
                                </span>
                                <span class="pp-mono text-xs" style="color: var(--ink-soft);">
                                    {{ trans_choice(':count letter|:count letters', $thread->letterCount, ['count' => $thread->letterCount]) }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-app-layout>