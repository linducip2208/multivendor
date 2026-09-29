@props(['paginator' => null])

@php
    $paginator = $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $paginator : null;

    if ($paginator) {
        $current = $paginator->currentPage();
        $last = max(1, (int) $paginator->lastPage());

        $window = [];
        for ($page = $current - 2; $page <= $current + 2; $page++) {
            if ($page >= 1 && $page <= $last) {
                $window[] = $page;
            }
        }

        $leading = $window !== [] && $window[0] > 1;
        $trailing = $window !== [] && end($window) < $last;
    }
@endphp

@if ($paginator && $paginator->hasPages())
    <nav class="sf-pagination" aria-label="Navigasi halaman">
        @if ($paginator->onFirstPage())
            <span class="is-disabled" aria-hidden="true">&larr;</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya">&larr;</a>
        @endif

        @if ($leading)
            <a href="{{ $paginator->url(1) }}" aria-label="Halaman 1">1</a>
            @if ($window[0] > 2)
                <span class="is-ellipsis" aria-hidden="true">&hellip;</span>
            @endif
        @endif

        @foreach ($window as $page)
            @if ($page === $current)
                <span class="is-active" aria-current="page">{{ $page }}</span>
            @else
                <a href="{{ $paginator->url($page) }}" aria-label="Halaman {{ $page }}">{{ $page }}</a>
            @endif
        @endforeach

        @if ($trailing)
            @if (end($window) < $last - 1)
                <span class="is-ellipsis" aria-hidden="true">&hellip;</span>
            @endif
            <a href="{{ $paginator->url($last) }}" aria-label="Halaman {{ $last }}">{{ $last }}</a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya">&rarr;</a>
        @else
            <span class="is-disabled" aria-hidden="true">&rarr;</span>
        @endif
    </nav>
@endif
