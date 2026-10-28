@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 pp-nav-caps leading-5 transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent pp-nav-caps leading-5 transition duration-150 ease-in-out';
$style = ($active ?? false)
            ? 'border-color: var(--accent); color: var(--ink);'
            : 'color: var(--ink-soft);';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} style="{{ $style }}">
    {{ $slot }}
</a>