@if ($paginator->hasPages())
<div class="d-flex justify-content-between align-items-center">
    <small class="text-muted" style="font-size:.75rem">
        Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }} hasil
    </small>
    <ul class="pagination pagination-sm mb-0" style="font-size:.75rem">
        @if ($paginator->onFirstPage())
            <li class="page-item disabled"><span class="page-link" style="font-size:.7rem;padding:3px 8px;">«</span></li>
        @else
            <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}" style="font-size:.7rem;padding:3px 8px;">«</a></li>
        @endif
        @foreach ($elements as $element)
            @if (is_string($element))
                <li class="page-item disabled"><span class="page-link" style="font-size:.7rem;padding:3px 8px;">{{ $element }}</span></li>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <li class="page-item active"><span class="page-link" style="font-size:.7rem;padding:3px 8px;">{{ $page }}</span></li>
                    @else
                        <li class="page-item"><a class="page-link" href="{{ $url }}" style="font-size:.7rem;padding:3px 8px;">{{ $page }}</a></li>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}" style="font-size:.7rem;padding:3px 8px;">»</a></li>
        @else
            <li class="page-item disabled"><span class="page-link" style="font-size:.7rem;padding:3px 8px;">»</span></li>
        @endif
    </ul>
</div>
@endif
