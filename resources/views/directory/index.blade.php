<x-app-layout>
    <x-slot name="header">
        <h2 class="pp-serif font-semibold text-xl" style="color: var(--ink);">
            {{ __('User Directory') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="pp-content-wrap space-y-4">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <form method="GET" action="{{ route('directory.index') }}" class="flex-1" style="min-width: 220px;">
                    <x-text-input type="text" name="q" value="{{ $query }}" placeholder="{{ __('Search by name…') }}"
                        class="w-full" />
                </form>
                <p class="pp-mono text-xs whitespace-nowrap" style="color: var(--ink-soft);">
                    {{ trans_choice(':count member|:count members', $totalMembers, ['count' => $totalMembers]) }}
                </p>
            </div>

            @forelse ($people as $person)
                <a href="{{ route('correspondence.show', $person) }}"
                    class="pp-paper-card p-4 sm:p-6 flex items-center justify-between gap-4 flex-wrap hover:opacity-90 transition-opacity"
                    style="text-decoration: none;">
                    <div>
                        <p class="pp-serif font-medium uppercase" style="color: var(--ink);">{{ $person->name }}</p>
                        <p class="pp-mono text-xs mt-1" style="color: var(--ink-soft);">
                            {{ __('Joined') }} {{ $person->created_at->format('M Y') }}
                        </p>
                    </div>
                </a>
            @empty
                <div class="pp-paper-card p-6 text-sm" style="color: var(--ink-soft);">
                    {{ __('No one matches that search.') }}
                </div>
            @endforelse

            {{ $people->links() }}
        </div>
    </div>
</x-app-layout>
