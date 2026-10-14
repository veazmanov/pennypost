@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}"
        class="flex items-center justify-between gap-4 flex-wrap pt-2">
        <p class="pp-mono text-xs" style="color: var(--ink-soft);">
            {{ __('Showing :first to :last of :total', [
                'first' => $paginator->firstItem(),
                'last' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ]) }}
        </p>

        <div class="flex items-center gap-2 flex-wrap">
            @if ($paginator->onFirstPage())
                <span class="pp-btn pp-btn-ghost" style="opacity: 0.4; cursor: default;" aria-disabled="true">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pp-btn pp-btn-ghost">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pp-mono text-xs" style="color: var(--ink-soft);" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pp-btn pp-btn-ghost" style="border-color: var(--ink);" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="pp-btn pp-btn-ghost"
                                aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pp-btn pp-btn-ghost">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="pp-btn pp-btn-ghost" style="opacity: 0.4; cursor: default;" aria-disabled="true">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>
    </nav>
@endif
