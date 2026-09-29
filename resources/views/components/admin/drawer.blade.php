@props([
    'id' => null,
    'title' => null,
    'side' => 'right',
    'width' => 420,
    'footer' => false,
])

@php
    $drawerId = $id ?: 'drawer-'.substr(md5((string) $title.'|'.$side), 0, 8);
    $side = $side === 'left' ? 'start' : 'end';
    $footerSlot = $footer instanceof \Illuminate\View\ComponentSlot ? $footer : null;
@endphp

<div
    class="offcanvas offcanvas-{{ $side }} admin-drawer"
    tabindex="-1"
    id="{{ $drawerId }}"
    aria-labelledby="{{ $drawerId }}-title"
    style="--tblr-offcanvas-width: {{ (int) $width }}px"
>
    <div class="offcanvas-header border-bottom">
        <h2 class="offcanvas-title h4 mb-0" id="{{ $drawerId }}-title">{{ $title }}</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>

    <div class="offcanvas-body">
        {{ $slot }}
    </div>

    @if ($footerSlot !== null || $footer === true)
        <div class="offcanvas-footer border-top p-3 d-flex gap-2 justify-content-end">
            @if ($footerSlot !== null)
                {{ $footerSlot }}
            @else
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="offcanvas">Tutup</button>
            @endif
        </div>
    @endif
</div>
