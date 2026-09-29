@props([
    'title' => null,
    'subtitle' => null,
    'footer' => false,
    'padding' => true,
    'icon' => null,
    'headerActions' => null,
    'flush' => false,
])

@php
    $footerSlot = $footer instanceof \Illuminate\View\ComponentSlot ? $footer : null;
    $headerHtml = match (true) {
        $headerActions instanceof \Illuminate\Contracts\Support\Htmlable => $headerActions->toHtml(),
        is_string($headerActions) => $headerActions,
        default => '',
    };
    $hasHeader = $title || $subtitle || $icon || $headerHtml !== '' || isset($actions) || isset($menu) || $flush;
@endphp

<div {{ $attributes->merge(['class' => 'card admin-card']) }}>
    @if ($hasHeader)
        <div class="card-header admin-card__header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
                @if ($icon)
                    <span class="text-primary d-inline-flex"><x-admin.icon :name="$icon" :size="20" /></span>
                @endif
                <div>
                    @if ($title)
                        <h3 class="card-title mb-0">{{ $title }}</h3>
                    @endif
                    @if ($subtitle)
                        <p class="card-subtitle text-secondary small mb-0 mt-1">{{ $subtitle }}</p>
                    @endif
                </div>
            </div>
            @if ($headerHtml !== '' || isset($actions) || isset($menu))
                <div class="d-flex align-items-center gap-2">
                    @if ($headerHtml !== '')
                        <div class="d-flex align-items-center gap-2">{!! $headerHtml !!}</div>
                    @endif
                    @isset($menu)
                        {{ $menu }}
                    @endisset
                    @isset($actions)
                        {{ $actions }}
                    @endisset
                </div>
            @endif
        </div>
    @endif

    <div class="card-body {{ $padding && ! $flush ? 'p-3' : 'p-0' }}">
        {{ $slot }}
    </div>

    @if ($footerSlot !== null || $footer === true)
        <div class="card-footer bg-transparent border-top">
            @if ($footerSlot !== null)
                {{ $footerSlot }}
            @endif
        </div>
    @endif
</div>
