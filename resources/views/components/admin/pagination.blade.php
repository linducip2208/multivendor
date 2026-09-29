@props([
    'paginator' => null,
    'size' => null,
    'showSummary' => true,
    'window' => 1,
])

@php
    $sizeClass = match ($size) {
        'sm' => 'pagination-sm',
        'lg' => 'pagination-lg',
        default => '',
    };

    $paginator = $paginator instanceof \Illuminate\Contracts\Pagination\Paginator ? $paginator : null;
    $isLengthAware = $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    $appends = request()->except('page');
    $pageUrl = function (int $pageNumber) use ($appends): string {
        return request()->fullUrlWithQuery(array_merge($appends, ['page' => $pageNumber]));
    };

    $current = 1;
    $last = 1;

    if ($paginator !== null) {
        $current = max(1, (int) $paginator->currentPage());
        $last = $isLengthAware ? max(1, (int) $paginator->lastPage()) : $current;
    }

    $windowSize = max(0, (int) $window);
    $start = max(1, $current - $windowSize);
    $end = min($last, $current + $windowSize);
    $pages = $start > 1 ? range(1, $end) : range($start, $end);

    if ($start > 1) {
        array_unshift($pages, 1);

        if ($start > 3) {
            array_splice($pages, 1, 0, ['...']);
        }
    }

    if ($end < $last) {
        $pages[] = $last;

        if ($end < $last - 2) {
            array_splice($pages, count($pages) - 1, 0, ['...']);
        }
    }

    $hasPrevious = $paginator !== null && $current > 1;
    $hasNext = $paginator !== null && ($isLengthAware ? $current < $last : $paginator->hasMorePages());
@endphp

@if ($paginator !== null && $paginator->hasPages())
    <nav aria-label="Navigasi halaman" class="admin-pagination d-flex flex-wrap align-items-center justify-content-between gap-2">
        @if ($showSummary)
            <p class="text-secondary small mb-0">
                Menampilkan {{ $paginator->firstItem() ?? 0 }}&ndash;{{ $paginator->lastItem() ?? 0 }}
                @if ($isLengthAware)
                    dari {{ $paginator->total() }} data
                @else
                    data pada halaman {{ $current }}
                @endif
            </p>
        @endif

        <ul class="pagination {{ $sizeClass }} mb-0">
            <li class="page-item {{ $hasPrevious ? '' : 'disabled' }}" @if (! $hasPrevious) aria-disabled="true" @endif>
                @if ($hasPrevious)
                    <a class="page-link" href="{{ $pageUrl($current - 1) }}" rel="prev" aria-label="Sebelumnya">&lsaquo;</a>
                @else
                    <span class="page-link">&lsaquo;</span>
                @endif
            </li>

            @foreach ($pages as $page)
                @if ($page === '...')
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">&hellip;</span></li>
                @else
                    <li class="page-item {{ $page === $current ? 'active' : '' }}">
                        <a
                            class="page-link"
                            href="{{ $pageUrl((int) $page) }}"
                            @if ($page === $current) aria-current="page" @endif
                        >{{ $page }}</a>
                    </li>
                @endif
            @endforeach

            <li class="page-item {{ $hasNext ? '' : 'disabled' }}" @if (! $hasNext) aria-disabled="true" @endif>
                @if ($hasNext)
                    <a class="page-link" href="{{ $pageUrl($current + 1) }}" rel="next" aria-label="Berikutnya">&rsaquo;</a>
                @else
                    <span class="page-link">&rsaquo;</span>
                @endif
            </li>
        </ul>
    </nav>
@elseif (isset($slot) && ! \Illuminate\Support\Str::of((string) $slot)->trim()->isEmpty())
    <nav aria-label="Navigasi halaman" class="admin-pagination">
        {{ $slot }}
    </nav>
@endif
