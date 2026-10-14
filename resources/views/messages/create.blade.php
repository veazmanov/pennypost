<x-app-layout>
    <x-slot name="header">
        <div style="display: grid; grid-template-columns: 1fr auto; align-items: center; gap: 16px;">
            <div>
                @if ($backTo)
                    <a href="{{ route('correspondence.show', $backTo) }}" class="pp-mono text-xs block mb-1"
                        style="color: var(--ink-soft); text-decoration: none;"
                        onmouseover="this.style.color='var(--ink)'"
                        onmouseout="this.style.color='var(--ink-soft)'">
                        ← {{ __('Back to') }} <span class="uppercase">{{ $backTo->name }}</span>
                    </a>
                @endif
                <h2 class="pp-serif font-semibold text-xl" style="color: var(--ink);">
                    {{ $letter->exists ? __('Edit your letter') : __('Write a letter') }}
                </h2>
            </div>

             <div class="flex items-center gap-3" x-data="{ submitting: false }"
                x-on:letter-submitting.window="submitting = true">
                <button type="button" @click="$dispatch('toggle-fullscreen')" class="pp-btn pp-btn-ghost"
                    :disabled="submitting">
                    {{ __('Full screen') }}
                </button>
                <button type="button" class="pp-btn pp-btn-ghost" :disabled="submitting"
                    @click="document.getElementById('intent-field').value = 'draft'; document.getElementById('letter-form').requestSubmit();">
                    {{ __('Save draft') }}
                </button>
                <button type="button" @click="$dispatch('open-modal', 'confirm-send')" class="pp-btn pp-btn-solid"
                    style="padding: 11px 28px; font-size: 15px;" :disabled="submitting">
                    {{ __('Seal & send') }}
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="pp-content-wrap" x-data="{
                query: @js(old('recipient_name', $letter->recipient->name ?? request('to_name', ''))),
                recipientId: @js(old('recipient_id', $letter->recipient_id ?? request('to_id', ''))),
                lastSelectedQuery: @js(old('recipient_name', $letter->recipient->name ?? request('to_name', ''))),
                results: [],
                open: false,
                body: @js(old('body', $letter->body ?? '')),
                enclosureUrls: @js(old('enclosures', $letter->enclosures ?? [])),
                fullscreen: false,
                submitting: false,
                init() {
                    this.$watch('fullscreen', (value) => {
                        document.body.classList.toggle('pp-fullscreen-editor', value);
                    });
                },
                search() {
                    if (this.query !== this.lastSelectedQuery) {
                        this.recipientId = '';
                    }

                    if (this.query.length < 1) { this.results = []; this.open = false; return; }
                    fetch(`{{ route('users.search') }}?q=${encodeURIComponent(this.query)}`)
                        .then(r => r.json())
                        .then(data => { this.results = data; this.open = data.length > 0; });
                },
                select(user) {
                    this.recipientId = user.id;
                    this.query = user.name;
                    this.lastSelectedQuery = user.name;
                    this.open = false;
                    this.results = [];
                },
            }" x-on:toggle-fullscreen.window="fullscreen = !fullscreen"
            x-on:keydown.escape.window="if (fullscreen) fullscreen = false">

            <div x-show="fullscreen" x-cloak class="mb-6">
                <button type="button" @click="fullscreen = false" class="pp-mono text-xs"
                    style="color: var(--ink-soft); background: none; border: none; padding: 0; cursor: pointer; text-decoration: underline; text-underline-offset: 3px;">
                    {{ __('← Exit full screen') }}
                </button>
            </div>

            @if (session('status') === 'draft-saved')
                @php
                    $cutoffAt = $nextBatch->copy()->subDays(config('pennypost.cutoff_days_before_batch'));
                    $totalMinutes = max(0, (int) now()->diffInMinutes($cutoffAt));
                    $daysRemaining = intdiv($totalMinutes, 1440);
                    $hoursRemaining = intdiv($totalMinutes % 1440, 60);
                    $minutesRemaining = $totalMinutes % 60;

                    $timeRemaining = match (true) {
                        $totalMinutes < 60 => trans_choice(':count minute|:count minutes', $minutesRemaining, ['count' => $minutesRemaining]),
                        $daysRemaining === 0 => trans_choice(':count hour|:count hours', $hoursRemaining, ['count' => $hoursRemaining]),
                        default => trans_choice(':count day|:count days', $daysRemaining, ['count' => $daysRemaining]),
                    };
                @endphp
                <div class="pp-stamp-badge text-sm mb-4">
                    {{ __('Draft saved. You have :time to send it in order to have your mail arrive :delivery.', [
                        'time' => $timeRemaining,
                        'delivery' => \App\Models\Message::humanDayLabel($nextBatch),
                    ]) }}
                </div>
            @endif

            <div class="pp-letter-plain p-8 sm:p-12" :class="{ 'pp-editor-fullscreen': fullscreen }">
                <form id="letter-form" method="POST"
                    action="{{ $letter->exists ? route('messages.update', $letter) : route('messages.store') }}"
                    @submit="submitting = true; $dispatch('letter-submitting')">
                    @csrf
                    <input type="hidden" name="intent" id="intent-field" value="draft">
                    @if ($letter->exists)
                        @method('PUT')
                    @endif

                    <div class="text-right">
                        <p class="pp-serif" style="color: var(--ink-soft);">
                            {{ now()->format('j F Y') }}
                        </p>
                    </div>

                    <div class="relative mt-6 mx-auto" style="width: fit-content;">
                        <div class="inline-flex items-baseline">
                            <label for="recipient_name" class="pp-serif shrink-0 italic"
                                style="color: var(--ink); font-size: 1.25rem; line-height: 1.75; margin-right: 0.75rem;">{{ __('To') }}</label>
                            <input id="recipient_name" type="text" autocomplete="off"
                                class="pp-input-line pp-input-line--flush pp-serif uppercase" style="width: 14rem;"
                                x-model="query" @input="search()">
                        </div>
                        <input type="hidden" name="recipient_id" x-model="recipientId">

                        <ul x-show="open" x-cloak @click.outside="open = false"
                            class="absolute z-10 mt-1 w-64 bg-[var(--paper-card)] border border-[var(--line)] shadow-lg divide-y divide-[var(--line)]">
                            <template x-for="user in results" :key="user.id">
                                <li @click="select(user)"
                                    class="px-4 py-2 text-sm text-[var(--ink)] hover:bg-[var(--paper)] cursor-pointer"
                                    x-text="user.name"></li>
                            </template>
                        </ul>

                        @error('recipient_id')
                            <p class="mt-2 text-sm text-[var(--error-red)]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mt-8">
                        <label for="body" class="sr-only">{{ __('Your letter') }}</label>
                        <textarea id="body" name="body" rows="10"
                            maxlength="{{ config('pennypost.max_letter_length') }}"
                            x-model="body"
                            class="pp-textarea-plain pp-serif mt-3 text-[1.25rem] leading-[1.75]"></textarea>
                        @error('body')
                            <p class="mt-2 text-sm text-[var(--error-red)]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mt-8 text-right">
                        <p class="pp-serif" style="color: var(--ink); font-size: 1.25rem; line-height: 1.75;">
                            [{{ auth()->user()->name }}]
                        </p>
                    </div>
                </form>
            </div>

            <div class="pp-compose-meta">
                <p class="pp-mono text-xs" style="color: var(--ink-soft); margin: 0;">
                    {{ __('Sending is final — letters can\'t be unsent or edited once sealed.') }}
                </p>
            </div>


            <div class="mt-6">
                <template x-if="enclosureUrls.length === 0">
                    <button type="button" @click="enclosureUrls.push('')"
                        class="pp-mono text-xs" style="color: var(--ink-soft); background: none; border: none; padding: 0; cursor: pointer; text-decoration: underline; text-underline-offset: 3px;">
                        {{ __('+ Enclose a link') }}
                    </button>
                </template>

                <div x-show="enclosureUrls.length > 0" x-cloak class="pp-card p-4 sm:p-6">
                    <x-input-label :value="__('Enclosed')" />

                    <div class="mt-1 space-y-2">
                        <template x-for="(url, index) in enclosureUrls" :key="index">
                            <div class="flex items-center gap-2">
                                <span x-show="enclosureUrls.length > 1" x-cloak x-text="'#' + (index + 1)"
                                    class="pp-mono text-xs shrink-0" style="color: var(--ink-soft); width: 1.5rem;"></span>
                                <input type="url" :name="`enclosures[${index}]`" form="letter-form" placeholder="https://…"
                                    x-model="enclosureUrls[index]" class="pp-enclosure-input flex-1">
                                <button type="button" @click="enclosureUrls.splice(index, 1)"
                                    class="pp-mono text-xs shrink-0"
                                    style="color: var(--ink-soft); background: none; border: none; cursor: pointer;"
                                    aria-label="{{ __('Remove this link') }}">
                                    {{ __('×') }}
                                </button>
                            </div>
                        </template>
                    </div>

                    <button type="button" @click="enclosureUrls.push('')"
                        class="pp-mono text-xs mt-3" style="color: var(--ink-soft); background: none; border: none; padding: 0; cursor: pointer; text-decoration: underline; text-underline-offset: 3px;">
                        {{ __('+ Add another link') }}
                    </button>

                    @error('enclosures')
                        <p class="mt-2 text-sm text-[var(--error-red)]">{{ $message }}</p>
                    @enderror
                    @error('enclosures.*')
                        <p class="mt-2 text-sm text-[var(--error-red)]">{{ __('One of those links doesn\'t look right.') }}</p>
                    @enderror
                </div>
            </div>

            <div x-show="fullscreen" x-cloak class="flex justify-end gap-3 mt-6 pt-6"
                style="border-top: 1px solid var(--line);">

                <button type="button" class="pp-btn pp-btn-ghost" :disabled="submitting"
                    @click="document.getElementById('intent-field').value = 'draft'; document.getElementById('letter-form').requestSubmit();">
                    {{ __('Save draft') }}
                </button>
                <button type="button" @click="$dispatch('open-modal', 'confirm-send')" class="pp-btn pp-btn-solid"
                    :disabled="submitting">
                    {{ __('Seal & send') }}
                </button>
            </div>
            <x-modal name="confirm-send" :show="false" maxWidth="md">
                <div class="p-6 sm:p-8">
                    <h2 class="pp-serif text-lg font-medium" style="color: var(--ink);">
                        {{ __('Seal this letter?') }}
                    </h2>
                    <p class="mt-3 text-sm" style="color: var(--ink-soft);">
                        {{ __('Sending is final. Once a letter is sealed there is no way to unsend, unseal, or edit it — not even before delivery.') }}
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" class="pp-btn pp-btn-ghost" :disabled="submitting"
                            @click="$dispatch('close-modal', 'confirm-send')">
                            {{ __('Keep editing') }}
                        </button>
                        <button type="button" class="pp-btn pp-btn-solid" :disabled="submitting"
                            @click="document.getElementById('intent-field').value = 'send'; document.getElementById('letter-form').requestSubmit();">
                            <span x-show="!submitting" x-cloak>{{ __('Yes, seal & send') }}</span>
                            <span x-show="submitting" x-cloak class="inline-flex items-center gap-2">
                                <span class="pp-spinner"></span>
                                {{ __('Sealing…') }}
                            </span>
                        </button>
                    </div>
                </div>
            </x-modal>

            @if ($letter->exists && $letter->is_draft)
                <form method="POST" action="{{ route('messages.destroy', $letter) }}"
                    onsubmit="return confirm('{{ __('Delete this draft for good?') }}')" class="mt-4 text-right">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="text-xs text-[var(--ink-soft)] hover:text-[var(--error-red)] pp-mono underline">
                        {{ __('Delete this draft') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>