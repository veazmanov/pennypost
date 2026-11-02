@props(['letter'])

@php($tooltipId = 'in-the-post-'.$letter->id)

{{-- The arrival time is formatted in the browser so it shows in the reader's own time zone. --}}
<span {{ $attributes->merge(['class' => 'relative inline-block']) }}
    x-data="{
        open: false,
        nudge: 0,
        arrivesAt: new Date('{{ $letter->scheduled_for->toIso8601String() }}'),
        time(date) {
            const style = window.innerWidth < 640 ? 'short' : 'long';
            return date.toLocaleString(undefined, {
                weekday: style, day: 'numeric', month: style, hour: '2-digit', minute: '2-digit', timeZoneName: 'short',
            });
        },
        {{-- Nudge the tooltip sideways so it stays at least 16px inside the viewport. --}}
        place() {
            const rect = this.$refs.tooltip.getBoundingClientRect();
            const gutter = 16;
            if (rect.left < gutter) {
                this.nudge = gutter - rect.left;
            } else if (rect.right > window.innerWidth - gutter) {
                this.nudge = window.innerWidth - gutter - rect.right;
            }
        },
    }"
    x-init="$watch('open', value => value ? $nextTick(() => requestAnimationFrame(() => place())) : nudge = 0)"
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
    <span id="{{ $tooltipId }}" role="tooltip" x-ref="tooltip"
        class="pp-mono text-xs absolute right-0 top-full mt-2 z-10"
        style="display: none; width: max-content; max-width: min(20rem, calc(100vw - 2rem)); background: var(--letter-ink); color: var(--letter-paper); border-radius: 2px; padding: 4px 8px;"
        x-bind:style="{ transform: 'translateX(' + nudge + 'px)' }"
        x-show="open">
        {{ __('Arrives') }} <span x-text="time(arrivesAt)">{{ $letter->scheduled_for->utc()->format('l j F, H:i') }} UTC</span>
    </span>
</span>
