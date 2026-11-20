<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <h2 class="pp-serif font-semibold text-xl" style="color: var(--ink);">
                {{ $person->name }}
            </h2>
            <a href="{{ route('messages.create', ['to_id' => $person->id, 'to_name' => $person->name]) }}"
                class="pp-btn pp-btn-solid">
                {{ __('Write') }}
                <x-icons.pen />
            </a>
        </div>
    </x-slot>
    {{-- `menu` holds the download links of whichever letter's cog was pressed last. --}}
    <div class="py-6 sm:py-12" x-data="{ menu: null }">
        <div class="mx-auto" style="max-width: 74rem; --pp-gutter: clamp(1.5rem, 6vw, 4rem); padding-inline: var(--pp-gutter);">


        @if ($letters->isNotEmpty())
            <div class="md:hidden mb-6 flex gap-2 overflow-x-auto pb-2" style="scrollbar-width: none;">
                @foreach ($letters as $letter)
                    <a href="#letter-{{ $letter->id }}" @class(['pp-mono text-xs shrink-0', 'font-bold' => $letter->isUnreadBy(auth()->user())])
                        style="color: var(--ink-soft); border: 1px solid var(--line); border-radius: 999px;
                               padding: 4px 10px; text-decoration: none; white-space: nowrap;">
                        {{ $letter->sent_at->format('j M') }}
                    </a>
                @endforeach
            </div>
        @endif


            <div class="flex items-start gap-10">
                @if ($letters->isNotEmpty())
                    <nav class="hidden md:block shrink-0"
                        style="width: 150px; position: sticky; top: 24px; max-height: calc(100vh - 48px); overflow-y: auto;">
                        <p class="pp-mono text-xs mb-3" style="color: var(--ink-soft); letter-spacing: 0.08em;">
                            {{ __('JUMP TO') }}
                        </p>
                        <ul class="space-y-2">
                            @foreach ($letters as $letter)
                                <li>
                                    <a href="#letter-{{ $letter->id }}" @class(['pp-mono text-xs block', 'font-bold' => $letter->isUnreadBy(auth()->user())])
                                        style="color: var(--ink-soft); text-decoration: none; line-height: 1.5;"
                                        onmouseover="this.style.color='var(--ink)'"
                                        onmouseout="this.style.color='var(--ink-soft)'">
                                        {{ $letter->sent_at->format('jS F') }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                @endif

                <div class="flex-1 min-w-0" style="max-width: 57.5rem;">
                    @if (session('status') === 'message-sent')
                        <div class="pp-stamp-badge text-sm mb-4">
                            {{ __('Sealed! Your letter will arrive on :day the :date.', [
                                'day' => session('deliveryDayName'),
                                'date' => session('deliveryDayOrdinal'),
                            ]) }}
                        </div>
                    @endif

                    <div class="pp-letter-plain pp-letter-bleed">
                        @forelse ($letters as $letter)
                            <div id="letter-{{ $letter->id }}" class="pp-letter-entry px-4 py-8 sm:p-12"
                                style="scroll-margin-top: 24px;">

                                <div class="flex items-baseline justify-end gap-4">
                                    @unless ($letter->isDelivered())
                                        <x-in-the-post :letter="$letter" />
                                    @endunless
                                    {{-- Bold for letters that were unread until this visit. --}}
                                    <p @class(['pp-serif', 'font-bold' => $letter->isUnreadBy(auth()->user())]) style="color: var(--ink-soft);">
                                        {{ $letter->sent_at->format('j F Y') }}
                                    </p>
                                </div>

                                <p class="pp-serif mt-6 text-center"
                                    style="color: var(--ink); font-size: 1.25rem; line-height: 1.75;">
                                    <span class="italic">{{ __('To') }}</span>
                                    <span class="uppercase" style="margin-left: 0.75rem;">
                                        {{ $letter->recipient_id === auth()->id() ? auth()->user()->name : $person->name }}
                                    </span>
                                </p>

                                <p class="mt-8 whitespace-pre-wrap pp-serif"
                                    style="color: var(--ink); font-size: 1.25rem; line-height: 1.75;">{{ $letter->body }}</p>

                                <p class="mt-8 text-right pp-serif"
                                    style="color: var(--ink); font-size: 1.25rem; line-height: 1.75;">
                                    [{{ $letter->sender_id === auth()->id() ? auth()->user()->name : $person->name }}]
                                </p>

                                @if ($letter->hasEnclosures())
                                    <div class="mt-8 space-y-3">
                                        <p class="pp-mono text-xs" style="color: var(--ink-soft); letter-spacing: 0.08em; margin: 0 0 4px 16px;">
                                            {{ __('ENCLOSED') }}
                                        </p>
                                        @foreach ($letter->enclosures as $url)
                                            <div class="pp-enclosure">
                                                @if (count($letter->enclosures) > 1)
                                                    <p class="pp-mono text-xs" style="color: var(--ink-soft); margin: 0 0 2px;">
                                                        {{ __('#:number', ['number' => $loop->iteration]) }}
                                                    </p>
                                                @endif
                                                <a href="{{ $url }}" target="_blank" rel="noopener" class="pp-mono"
                                                   style="color: var(--ink); text-decoration: underline; text-underline-offset: 3px;">
                                                    {{ $url }}
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="mt-6 flex justify-end">
                                    <button type="button" class="pp-letter-menu-btn"
                                        aria-label="{{ __('Letter options') }}"
                                        @if ($letter->isDelivered())
                                            data-pdf="{{ route('messages.download', [$letter, 'pdf']) }}"
                                            data-txt="{{ route('messages.download', [$letter, 'txt']) }}"
                                        @endif
                                        @click="menu = $el.dataset.pdf ? { pdf: $el.dataset.pdf, txt: $el.dataset.txt } : null; $dispatch('open-modal', 'letter-menu')">
                                        <x-icons.cog />
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="px-4 py-8 sm:p-12 text-sm" style="color: var(--ink-soft);">
                                {{ __('Nothing here yet.') }}
                            </div>
                        @endforelse
                    </div>

                    <x-modal name="letter-menu" maxWidth="sm">
                        <nav class="pp-letter-menu">
                            {{-- Letters still in the post can't be downloaded yet. --}}
                            <template x-if="menu">
                                <a :href="menu.pdf" download @click="$dispatch('close-modal', 'letter-menu')">
                                    {{ __('Download as PDF') }}
                                </a>
                            </template>
                            <template x-if="menu">
                                <a :href="menu.txt" download @click="$dispatch('close-modal', 'letter-menu')">
                                    {{ __('Download as TXT') }}
                                </a>
                            </template>
                            <a href="{{ route('messages.create', ['to_id' => $person->id, 'to_name' => $person->name]) }}">
                                {{ __('Write back') }}
                            </a>
                        </nav>
                    </x-modal>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
