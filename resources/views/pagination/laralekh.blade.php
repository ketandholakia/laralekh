@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="x-pagination">
        <p class="x-pagination-summary hidden sm:block">
            {{ __('Showing') }}
            @if ($paginator->firstItem())
                <span class="font-medium text-slate-900">{{ $paginator->firstItem() }}</span>–<span class="font-medium text-slate-900">{{ $paginator->lastItem() }}</span>
            @else
                <span class="font-medium text-slate-900">{{ $paginator->count() }}</span>
            @endif
            {{ __('of') }} <span class="font-medium text-slate-900">{{ $paginator->total() }}</span> {{ __('results') }}
        </p>

        <div class="x-pagination-nav">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span class="x-pagination-item is-disabled" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                    <x-icon name="arrow-left" class="h-4 w-4"/>
                    <span class="sr-only">{{ __('pagination.previous') }}</span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="x-pagination-item" aria-label="{{ __('pagination.previous') }}">
                    <x-icon name="arrow-left" class="h-4 w-4"/>
                    <span class="sr-only">{{ __('pagination.previous') }}</span>
                </a>
            @endif

            {{-- Page elements --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="x-pagination-item is-disabled" aria-disabled="true"><span class="sr-only">{{ $element }}</span><span aria-hidden="true">…</span></span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="x-pagination-item" aria-current="page" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="x-pagination-item" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="x-pagination-item" aria-label="{{ __('pagination.next') }}">
                    <x-icon name="arrow-right" class="h-4 w-4"/>
                    <span class="sr-only">{{ __('pagination.next') }}</span>
                </a>
            @else
                <span class="x-pagination-item is-disabled" aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                    <x-icon name="arrow-right" class="h-4 w-4"/>
                    <span class="sr-only">{{ __('pagination.next') }}</span>
                </span>
            @endif
        </div>
    </nav>
@endif
