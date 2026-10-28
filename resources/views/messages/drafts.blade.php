<x-app-layout>
    <x-slot name="header">
        <h2 class="pp-serif font-semibold text-xl text-[var(--ink)] leading-tight">
            {{ __('Drafts') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="pp-content-wrap space-y-4">
            @if (session('status') === 'draft-deleted')
                <div class="pp-stamp-badge text-sm">{{ __('Draft deleted.') }}</div>
            @endif

            @forelse ($drafts as $draft)
                <div class="pp-paper-card p-4 sm:p-6">
                    <div class="flex items-baseline justify-between gap-4 flex-wrap">
                        <p class="pp-serif font-medium" style="color: var(--ink);">
                            {{ $draft->recipient->name ?? __('No recipient yet') }}
                        </p>
                        <span class="pp-mono text-xs" style="color: var(--ink-soft);">
                            {{ __('Last touched') }} {{ $draft->updated_at->diffForHumans() }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm line-clamp-2" style="color: var(--ink-soft);">
                        {{ $draft->body !== '' ? $draft->body : __('(empty letter)') }}
                    </p>

                    <div class="mt-3 pt-3 flex items-center gap-4" style="border-top: 1px dashed var(--line);">
                        <a href="{{ route('messages.edit', $draft) }}"
                            style="font-size: 13px; color: var(--ink-soft); text-decoration: none;"
                            onmouseover="this.style.textDecoration='underline'; this.style.color='var(--ink)'"
                            onmouseout="this.style.textDecoration='none'; this.style.color='var(--ink-soft)'">
                            {{ __('Edit') }}
                        </a>

                        <form method="POST" action="{{ route('messages.destroy', $draft) }}"
                            onsubmit="return confirm('{{ __('Delete this draft for good?') }}')" style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                style="font-size: 13px; color: var(--ink-soft); text-decoration: none; background: none; border: none; padding: 0; font-family: inherit; cursor: pointer;"
                                onmouseover="this.style.textDecoration='underline'; this.style.color='var(--error-red)'"
                                onmouseout="this.style.textDecoration='none'; this.style.color='var(--ink-soft)'">
                                {{ __('Delete') }}
                            </button>
                        </form>

                        <x-download-links :message="$draft" class="ml-auto" />
                    </div>
                </div>
            @empty
                <div class="pp-paper-card p-6 text-sm" style="color: var(--ink-soft);">
                    {{ __("No drafts sitting around. Start one whenever you're ready.") }}
                </div>
            @endforelse

            {{ $drafts->links() }}
        </div>
    </div>
</x-app-layout>