<x-app-layout>
    <x-slot name="header">
        <h2 class="pp-serif font-semibold text-xl text-[var(--ink)] leading-tight">
            {{ __('Drafts') }}
        </h2>
    </x-slot>

    {{-- `menu` holds the links of whichever draft's cog was pressed last. --}}
    <div class="py-12" x-data="{ menu: {} }">
        <div class="pp-content-wrap space-y-4">
            @if (session('status') === 'draft-deleted')
                <div class="pp-stamp-badge text-sm">{{ __('Draft deleted.') }}</div>
            @endif

            @forelse ($drafts as $draft)
                <div class="pp-paper-card p-4 sm:p-6">
                    <div class="flex items-baseline justify-between gap-4 flex-wrap">
                        <a href="{{ route('messages.edit', $draft) }}" class="pp-thread-link">
                            <span class="pp-serif font-medium" style="color: var(--ink);">
                                {{ $draft->recipient->name ?? __('No recipient yet') }}
                            </span>
                        </a>
                        <span class="pp-mono text-xs" style="color: var(--ink-soft);">
                            {{ __('Last touched') }} {{ $draft->updated_at->diffForHumans() }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm line-clamp-2" style="color: var(--ink-soft);">
                        {{ $draft->body !== '' ? $draft->body : __('(empty letter)') }}
                    </p>

                    <div class="mt-3 flex justify-end">
                        <button type="button" class="pp-letter-menu-btn"
                            aria-label="{{ __('Draft options') }}"
                            data-edit="{{ route('messages.edit', $draft) }}"
                            data-pdf="{{ route('messages.download', [$draft, 'pdf']) }}"
                            data-txt="{{ route('messages.download', [$draft, 'txt']) }}"
                            data-destroy="{{ route('messages.destroy', $draft) }}"
                            @click="menu = { ...$el.dataset }; $dispatch('open-modal', 'draft-menu')">
                            <x-icons.cog />
                        </button>
                    </div>
                </div>
            @empty
                <div class="pp-paper-card p-6 text-sm" style="color: var(--ink-soft);">
                    {{ __("No drafts sitting around. Start one whenever you're ready.") }}
                </div>
            @endforelse

            {{ $drafts->links() }}

            {{-- Outside the menu so its Delete button can sit in the list with the links. --}}
            <form id="delete-draft-form" method="POST" class="hidden" :action="menu.destroy"
                @submit="if (!confirm(@js(__('Delete this draft for good?')))) $event.preventDefault()">
                @csrf
                @method('DELETE')
            </form>

            <x-modal name="draft-menu" maxWidth="sm">
                <nav class="pp-letter-menu">
                    <a :href="menu.edit">{{ __('Edit') }}</a>
                    <a :href="menu.pdf" download @click="$dispatch('close-modal', 'draft-menu')">
                        {{ __('Download as PDF') }}
                    </a>
                    <a :href="menu.txt" download @click="$dispatch('close-modal', 'draft-menu')">
                        {{ __('Download as TXT') }}
                    </a>
                    <button type="submit" form="delete-draft-form">{{ __('Delete draft') }}</button>
                </nav>
            </x-modal>
        </div>
    </div>
</x-app-layout>