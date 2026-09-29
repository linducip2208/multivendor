@props([
    'product' => null,
    'nav' => 'admin',
    'size' => 40,
    'showSku' => true,
    'route' => 'edit',
])

@php
    $product = $product;
    $nav = $nav === 'vendor' ? 'vendor' : 'admin';
    $routeMap = $route === 'show'
        ? [$nav.'.products.show', $nav.'.products.index']
        : [$nav.'.products.edit', $nav.'.products.show', $nav.'.products.index'];

    $url = null;

    if ($product !== null) {
        foreach ($routeMap as $candidate) {
            if (\Route::has($candidate)) {
                try {
                    $url = route($candidate, $product);
                } catch (\Throwable $e) {
                    $url = null;
                }
                if ($url !== null) {
                    break;
                }
            }
        }
    }
@endphp

@if ($product !== null)
    @php
        $name = (string) ($product->name ?? $product->title ?? $product->sku ?? 'Produk');
        $sku = $product->sku ?? $product->code ?? null;
        $image = $product->thumbnail ?? $product->image ?? null;
        $link = $url ?: request()->fullUrl();
    @endphp

    <div {{ $attributes->merge(['class' => 'd-flex align-items-center gap-2 admin-product-cell']) }}>
        <span class="flex-shrink-0 admin-product-cell__thumb" style="width: {{ (int) $size }}px; height: {{ (int) $size }}px;">
            @if ($image)
                <img src="{{ $image }}" alt="{{ $name }}" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
            @else
                <span class="d-flex align-items-center justify-content-center w-100 h-100 text-secondary bg-secondary-lt">
                    <x-admin.icon name="package" :size="18" />
                </span>
            @endif
        </span>

        <span class="flex-grow-1">
            <a href="{{ $link }}" class="text-reset d-block text-truncate fw-medium">{{ $name }}</a>
            @if ($showSku && $sku)
                <span class="d-block text-secondary small text-truncate">SKU: {{ $sku }}</span>
            @endif
        </span>
    </div>
@endif
