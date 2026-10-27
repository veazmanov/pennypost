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
                <button type="button" @click="$dispatch('request-send')" class="pp-btn pp-btn-solid"
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
                recipientProblem: '',
                bodyProblem: '',
                storageKey: @js('pp-letter:'.auth()->id().':'.($letter->id ?? 'new')),
                serverCopy: null,
                restoredLocally: false,
                dirty: false,
                saveTimer: null,
                init() {
                    this.$watch('fullscreen', (value) => {
                        document.body.classList.toggle('pp-fullscreen-editor', value);
                    });

                    this.serverCopy = this.snapshot();
                    this.restoreLocal();

                    ['body', 'query', 'recipientId', 'enclosureUrls'].forEach((field) => {
                        this.$watch(field, () => {
                            this.dirty = true;
                            clearTimeout(this.saveTimer);
                            this.saveTimer = setTimeout(() => this.saveLocal(), 500);
                        });
                    });
                    this.$watch('recipientId', (id) => { if (id) this.recipientProblem = ''; });
                    this.$watch('body', (body) => { if (body.trim()) this.bodyProblem = ''; });

                    window.addEventListener('beforeunload', (event) => {
                        if (this.dirty && !this.submitting) {
                            event.preventDefault();
                            event.returnValue = '';
                        }
                    });

                    // Keeps the session (and its CSRF token) alive while the letter is open.
                    setInterval(() => this.refreshCsrfToken(), 10 * 60 * 1000);
                },
                snapshot() {
                    return {
                        body: this.body,
                        query: this.query,
                        recipientId: this.recipientId,
                        enclosureUrls: [...this.enclosureUrls],
                    };
                },
                saveLocal() {
                    try {
                        localStorage.setItem(this.storageKey, JSON.stringify({ ...this.snapshot(), savedAt: Date.now() }));
                    } catch (e) {}
                },
                restoreLocal() {
                    let stored = null;
                    try {
                        stored = JSON.parse(localStorage.getItem(this.storageKey));
                    } catch (e) {}

                    if (!stored || typeof stored.body !== 'string' || stored.body === '' || stored.body === this.body) return;

                    this.body = stored.body;
                    this.query = stored.query ?? this.query;
                    this.lastSelectedQuery = this.query;
                    this.recipientId = stored.recipientId ?? this.recipientId;
                    this.enclosureUrls = Array.isArray(stored.enclosureUrls) ? stored.enclosureUrls : this.enclosureUrls;
                    this.restoredLocally = true;
                    this.$nextTick(() => { this.dirty = true; });
                },
                discardLocal() {
                    try { localStorage.removeItem(this.storageKey); } catch (e) {}
                    clearTimeout(this.saveTimer);
                    Object.assign(this, { ...this.serverCopy, lastSelectedQuery: this.serverCopy.query, enclosureUrls: [...this.serverCopy.enclosureUrls] });
                    this.restoredLocally = false;
                    // The watchers fire after this tick; undo what they schedule.
                    setTimeout(() => { clearTimeout(this.saveTimer); this.dirty = false; });
                },
                refreshCsrfToken() {
                    fetch(@js(route('csrf-token')), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                        .then(r => r.ok ? r.json() : null)
                        .then(data => {
                            if (!data?.token) return;
                            document.querySelectorAll('input[name=_token]').forEach(input => input.value = data.token);
                            document.querySelector('meta[name=csrf-token]')?.setAttribute('content', data.token);
                        })
                        .catch(() => {});
                },
                search() {
                    if (this.query !== this.lastSelectedQuery) {
                        this.recipientId = '';
                    }

                    const query = this.query;
                    if (query.length < 1) { this.results = []; this.open = false; return; }
                    fetch(`{{ route('users.search') }}?q=${encodeURIComponent(query)}`)
                        .then(r => r.json())
                        .then(data => {
                            if (query !== this.query) return; // a newer search is on its way
                            this.results = data;
                            this.open = data.length > 0;
                            this.selectExactMatch();
                        });
                },
                selectExactMatch() {
                    const typed = this.query.trim().toLowerCase();
                    const exact = this.results.filter(user => user.name.trim().toLowerCase() === typed);
                    if (exact.length === 1 && !this.recipientId) {
                        this.recipientId = exact[0].id;
                        this.lastSelectedQuery = this.query;
                    }
                },
                select(user) {
                    this.recipientId = user.id;
                    this.query = user.name;
                    this.lastSelectedQuery = user.name;
                    this.open = false;
                    this.results = [];
                },
                requestSend() {
                    if (!this.recipientId) this.selectExactMatch();

                    this.recipientProblem = '';
                    this.bodyProblem = '';

                    if (!this.recipientId) {
                        this.recipientProblem = this.query.trim()
                            ? @js(__("We couldn't find that person — pick them from the suggestions before sending."))
                            : @js(__('Who is this letter to? Type their name and pick them from the suggestions.'));
                        this.$refs.recipient.focus();
                        return;
                    }

                    if (!this.body.trim()) {
                        this.bodyProblem = @js(__('Your letter is empty — write something before sealing it.'));
                        this.$refs.body.focus();
                        return;
                    }

                    this.$dispatch('open-modal', 'confirm-send');
                },
            }" x-on:toggle-fullscreen.window="fullscreen = !fullscreen"
            x-on:request-send.window="requestSend()"
            x-on:keydown.escape.window="if (fullscreen) fullscreen = false">

            <div x-show="fullscreen" x-cloak class="mb-6">
                <button type="button" @click="fullscreen = false" class="pp-mono text-xs"
                    style="color: var(--ink-soft); background: none; border: none; padding: 0; cursor: pointer; text-decoration: underline; text-underline-offset: 3px;">
                    {{ __('← Exit full screen') }}
                </button>
            </div>

            @if (session('status') === 'send-failed' || $errors->has('session'))
                <div class="pp-banner mb-4" role="alert"
                    style="flex-direction: column; align-items: flex-start; margin-top: 0; border-color: var(--error-red); color: var(--error-red);">
                    @if (session('status') === 'send-failed')
                        <strong>{{ __('Your letter was NOT sent. It\'s been saved as a draft.') }}</strong>
                    @endif
                    <ul class="text-sm" style="margin: 0; padding-left: 1.25rem; list-style: disc;">
                        @foreach (array_unique($errors->all()) as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div x-show="restoredLocally" x-cloak class="pp-banner mb-4" style="margin-top: 0;">
                <span>{{ __('Restored unsaved text from this browser.') }}</span>
                <button type="button" @click="discardLocal()" class="pp-mono text-xs"
                    style="color: inherit; background: none; border: none; padding: 0; cursor: pointer; text-decoration: underline; text-underline-offset: 3px;">
                    {{ __('discard') }}
                </button>
            </div>

            @if (session('status') === 'draft-saved')
                @php
                    $cutoffAt = \App\Models\Message::cutoffFor($nextBatch);
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

                    <div class="relative mt-6 mx-auto w-fit max-w-full">
                        <div class="inline-flex items-baseline max-w-full">
                            <label for="recipient_name" class="pp-serif shrink-0 italic"
                                style="color: var(--ink); font-size: 1.25rem; line-height: 1.75; margin-right: 0.75rem;">{{ __('To') }}</label>
                            {{-- The hidden copy of the name sizes the grid cell, so the input grows with what's typed. --}}
                            <span class="inline-grid min-w-0">
                                <span aria-hidden="true"
                                    class="pp-input-line pp-input-line--flush pp-serif uppercase [grid-area:1/1] invisible whitespace-pre min-w-56 pr-[1ch]"
                                    x-text="query || ' '"></span>
                                {{-- Enter here must never submit: the form's default intent is a draft, so it would quietly save an unaddressed one. --}}
                                <input id="recipient_name" name="recipient_name" type="text" autocomplete="off"
                                    class="pp-input-line pp-input-line--flush pp-serif uppercase [grid-area:1/1] min-w-0"
                                    x-ref="recipient" x-model="query" @input="search()"
                                    @keydown.enter.prevent="if (open && results.length) select(results[0])">
                            </span>
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

                        <p x-show="recipientProblem" x-cloak x-text="recipientProblem" role="alert"
                            class="mt-2 text-sm text-[var(--error-red)]"></p>
                        @error('recipient_id')
                            <p class="mt-2 text-sm text-[var(--error-red)]">{{ $message }}</p>
                        @enderror
                        @if (session('recipientWarning'))
                            <p class="mt-2 text-sm text-[var(--error-red)]">{{ session('recipientWarning') }}</p>
                        @endif
                    </div>

                    <div class="mt-8">
                        <label for="body" class="sr-only">{{ __('Your letter') }}</label>
                        <textarea id="body" name="body" rows="10"
                            maxlength="{{ config('pennypost.max_letter_length') }}"
                            x-ref="body" x-model="body"
                            class="pp-textarea-plain pp-serif mt-3 text-[1.25rem] leading-[1.75]"></textarea>
                        <p x-show="bodyProblem" x-cloak x-text="bodyProblem" role="alert"
                            class="mt-2 text-sm text-[var(--error-red)]"></p>
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
                                    x-model="enclosureUrls[index]" @keydown.enter.prevent class="pp-enclosure-input flex-1">
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
                <button type="button" @click="$dispatch('request-send')" class="pp-btn pp-btn-solid"
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
                <form method="POST" action="{{ route('messages.destroy', $letter) }}" class="mt-4 text-right"
                    @submit="if (!confirm(@js(__('Delete this draft for good?')))) { $event.preventDefault(); return; } submitting = true">
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