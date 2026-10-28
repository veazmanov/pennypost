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
    <div class="py-12">
        <div class="mx-auto" style="max-width: 74rem; padding-inline: clamp(1.5rem, 6vw, 4rem);">


        @if ($letters->isNotEmpty() || $drafts->isNotEmpty())
            <div class="md:hidden mb-6 flex gap-2 overflow-x-auto pb-2" style="scrollbar-width: none;">
                @foreach ($drafts as $draft)
                    <a href="{{ route('messages.edit', $draft) }}" class="pp-mono text-xs shrink-0"
                        style="color: var(--ink-soft); border: 1px solid var(--line); border-radius: 999px;
                               padding: 4px 10px; text-decoration: none; white-space: nowrap;">
                        {{ __('Edit draft') }} <span class="italic">· {{ __('not sent') }}</span>
                    </a>
                @endforeach
                @foreach ($letters as $letter)
                    <a href="#letter-{{ $letter->id }}" class="pp-mono text-xs shrink-0"
                        style="color: var(--ink-soft); border: 1px solid var(--line); border-radius: 999px;
                               padding: 4px 10px; text-decoration: none; white-space: nowrap;">
                        {{ $letter->sent_at->format('j M') }}
                        @unless ($letter->isDelivered())
                            <span class="italic">· {{ __('in the post') }}</span>
                        @endunless
                    </a>
                @endforeach
            </div>
        @endif


            <div class="flex items-start gap-10">
                                @if ($letters->isNotEmpty() || $drafts->isNotEmpty())
                    <nav class="hidden md:block shrink-0"
                        style="width: 150px; position: sticky; top: 24px; max-height: calc(100vh - 48px); overflow-y: auto;">
                        <p class="pp-mono text-xs mb-3" style="color: var(--ink-soft); letter-spacing: 0.08em;">
                            {{ __('JUMP TO') }}
                        </p>
                        <ul class="space-y-2">
                            @foreach ($drafts as $draft)
                                <li class="pb-2">
                                    <a href="{{ route('messages.edit', $draft) }}" class="pp-btn pp-btn-ghost">
                                        {{ __('Edit draft') }}
                                        <x-icons.pen />
                                    </a>
                                    <span class="pp-mono text-xs italic block mt-1" style="color: var(--ink-soft);">
                                        {{ __('not sent') }}
                                    </span>
                                </li>
                            @endforeach
                            @foreach ($letters as $letter)
                                <li>
                                    <a href="#letter-{{ $letter->id }}" class="pp-mono text-xs block"
                                        style="color: var(--ink-soft); text-decoration: none; line-height: 1.5;"
                                        onmouseover="this.style.color='var(--ink)'"
                                        onmouseout="this.style.color='var(--ink-soft)'">
                                        {{ $letter->sent_at->format('jS F') }}
                                        @unless ($letter->isDelivered())
                                            <span class="italic block">{{ __('in the post') }}</span>
                                        @endunless
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

                    <div class="pp-letter-plain">
                        @foreach ($drafts as $draft)
                            <a href="{{ route('messages.edit', $draft) }}" id="draft-{{ $draft->id }}"
                                class="pp-letter-entry p-8 sm:p-12 block"
                                style="scroll-margin-top: 24px; color: var(--ink-soft); text-decoration: none; cursor: text;"
                                onmouseover="this.style.color='var(--ink)'"
                                onmouseout="this.style.color='var(--ink-soft)'">
                                <p class="pp-serif text-center"
                                    style="font-size: 1.25rem; line-height: 1.75;">
                                    <span class="italic">{{ __('To') }}</span>
                                    <span class="uppercase" style="margin-left: 0.75rem;">{{ $person->name }}</span>
                                </p>

                                <p class="mt-8 whitespace-pre-wrap pp-serif"
                                    style="font-size: 1.25rem; line-height: 1.75;">{{ $draft->body }}</p>

                                <p class="mt-8 text-right pp-serif"
                                    style="font-size: 1.25rem; line-height: 1.75;">
                                    [{{ auth()->user()->name }}]
                                </p>
                            </a>
                        @endforeach

                        @forelse ($letters as $letter)
                            <div id="letter-{{ $letter->id }}" class="pp-letter-entry p-8 sm:p-12"
                                style="scroll-margin-top: 24px;">

                                <div class="flex items-baseline justify-end gap-4">
                                    @if ($letter->isDelivered())
                                        <x-download-links :message="$letter" class="mr-auto" />
                                    @endif
                                    <p class="pp-serif" style="color: var(--ink-soft);">
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
                            </div>
                        @empty
                            @if ($drafts->isEmpty())
                            <div class="p-8 sm:p-12 text-sm" style="color: var(--ink-soft);">
                                {{ __('Nothing here yet.') }}
                            </div>
                            @endif
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
