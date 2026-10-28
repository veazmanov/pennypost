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

    <div class="py-12">
        <div class="pp-content-wrap">
            @if ($correspondences->isEmpty())
                <div class="pp-paper-card p-6 text-sm space-y-3" style="color: var(--ink-soft);">
                    <p>{{ __("You don't have any delivered correspondence yet.") }}</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($correspondences as $thread)
                        <a href="{{ route('correspondence.show', $thread->person) }}"
                            class="pp-paper-card p-4 sm:p-6 flex items-center justify-between gap-4">
                            <p class="pp-serif font-medium uppercase" style="color: var(--ink);">
                                {{ $thread->person->name }}
                            </p>
                            <span class="pp-mono text-xs" style="color: var(--ink-soft);">
                                {{ trans_choice(':count letter|:count letters', $thread->letterCount, ['count' => $thread->letterCount]) }}
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>