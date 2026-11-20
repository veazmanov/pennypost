@props(['size' => 14])

{{-- An envelope with speed lines: a letter on its way. --}}
<svg width="{{ $size * 24 / 18 }}" height="{{ $size }}" viewBox="0 0 24 18" fill="none" stroke="currentColor"
    stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}>
    <rect x="8" y="3" width="15" height="12" rx="1" />
    <path d="M8.5 3.5l7 6 7-6" />
    <path d="M1 6h4M2.5 9H5M1 12h4" />
</svg>
