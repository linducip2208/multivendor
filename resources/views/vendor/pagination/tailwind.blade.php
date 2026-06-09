@if ($paginator->hasPages())
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <small style="font-size:.7rem;color:#6b7280">
        {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }}
    </small>
    <ul class="pagination pagination-sm mb-0">
        @if ($paginator->onFirstPage())
            <li class="page-item disabled"><span class="page-link" style="font-size:.65rem;padding:2px 6px;line-height:1.4">«</span></li>
        @else
            <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}" style="font-size:.65rem;padding:2px 6px;line-height:1.4">«</a></li>
        @endif
        @foreach ($elements as $element)
            @if (is_string($element))
                <li class="page-item disabled"><span class="page-link" style="font-size:.65rem;padding:2px 6px;line-height:1.4">…</span></li>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <li class="page-item active"><span class="page-link" style="font-size:.65rem;padding:2px 6px;line-height:1.4">{{ $page }}</span></li>
                    @else
                        <li class="page-item"><a class="page-link" href="{{ $url }}" style="font-size:.65rem;padding:2px 6px;line-height:1.4">{{ $page }}</a></li>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}" style="font-size:.65rem;padding:2px 6px;line-height:1.4">»</a></li>
        @else
            <li class="page-item disabled"><span class="page-link" style="font-size:.65rem;padding:2px 6px;line-height:1.4">»</span></li>
        @endif
    </ul>
</div>
@endif
