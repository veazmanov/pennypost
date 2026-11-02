@props(['letter'])

@php($tooltipId = 'in-the-post-'.$letter->id)

{{-- The arrival time is formatted in the browser so it shows in the reader's own time zone. --}}
<span {{ $attributes->merge(['class' => 'relative inline-block']) }}
    x-data="{
        open: false,
        arrivesAt: new Date('{{ $letter->scheduled_for->toIso8601String() }}'),
        time(date) {
            return date.toLocaleString(undefined, {
                weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit', timeZoneName: 'short',
            });
        },
    }"
    x-on:mouseenter="open = true"
    x-on:mouseleave="open = false"
    x-on:click.outside="open = false"
    x-on:keydown.escape.window="open = false">
    <button type="button" class="pp-nav-caps"
        style="color: var(--ink-soft); border: 1px dashed var(--line); border-radius: 2px; padding: 0 6px; cursor: help;"
        aria-describedby="{{ $tooltipId }}"
        x-bind:aria-expanded="open.toString()"
        aria-expanded="false"
        x-on:click="open = !open">{{ __('in the post') }}</button>
    <span id="{{ $tooltipId }}" role="tooltip"
        class="pp-mono text-xs absolute right-0 top-full mt-2 z-10 whitespace-nowrap"
        style="display: none; background: var(--letter-ink); color: var(--letter-paper); border-radius: 2px; padding: 4px 8px;"
        x-show="open">
        {{ __('Arrives') }} <span x-text="time(arrivesAt)">{{ $letter->scheduled_for->utc()->format('l j F, H:i') }} UTC</span>
    </span>
</span>
