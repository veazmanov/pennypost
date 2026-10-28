@props(['message'])

<span {{ $attributes->merge(['class' => 'pp-mono text-xs inline-flex items-center gap-3 text-[var(--ink-soft)]']) }}>
    @foreach (['pdf' => __('PDF'), 'txt' => __('TXT')] as $format => $label)
        <a href="{{ route('messages.download', [$message, $format]) }}" download
            class="no-underline hover:underline hover:text-[var(--ink)]"
            title="{{ __('Download as :format', ['format' => $label]) }}">{{ $label }}</a>
    @endforeach
</span>
