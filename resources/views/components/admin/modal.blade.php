@props([
    'id' => null,
    'title' => null,
    'size' => null,
    'footer' => false,
    'icon' => null,
    'staticBackdrop' => false,
])

@php
    $modalId = $id ?: 'modal-'.substr(md5((string) $title.'|'.(string) $size), 0, 8);
    $sizeClass = in_array($size, ['sm', 'lg', 'xl'], true) ? ' modal-'.$size : '';
    $footerSlot = $footer instanceof \Illuminate\View\ComponentSlot ? $footer : null;
@endphp

<div
    {{ $attributes->merge(['class' => 'modal fade']) }}
    id="{{ $modalId }}"
    tabindex="-1"
    aria-labelledby="{{ $modalId }}-title"
    aria-hidden="true"
    @if ($staticBackdrop) data-bs-backdrop="static" @endif
>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable{{ $sizeClass }}" role="document">
        <div class="modal-content">
            @if ($title || isset($header))
                <div class="modal-header">
                    <h5 class="modal-title" id="{{ $modalId }}-title">
                        @if ($icon)
                            <x-admin.icon :name="$icon" :size="18" class="me-1" />
                        @endif
                        {{ $title }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
            @endif

            <div class="modal-body">
                @isset($header)
                    {{ $header }}
                @endisset
                {{ $slot }}
            </div>

            @if ($footerSlot !== null || $footer === true)
                <div class="modal-footer">
                    @if ($footerSlot !== null)
                        {{ $footerSlot }}
                    @else
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
