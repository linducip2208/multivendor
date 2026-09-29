@props([
    'product',
    'wishlisted' => false,
    'showShop' => true,
    'ratio' => null,
])

{{-- Defensive: callers occasionally pass null/ids/strings (deleted or
     unresolved products). Never 500 the whole page for one bad item. --}}
@if (is_object($product))
@php
    $price = (float) ($product->effective_price ?? $product->price);
    $was = (float) $product->price;
    $discount = ($was > 0 && $price < $was) ? (int) round((($was - $price) / $was) * 100) : 0;
    $stock = (int) ($product->current_stock ?? 0);
    $inStock = $stock > 0;
    $isLow = $inStock && ($product->is_low_stock ?? $stock <= 5);
    $rating = (float) ($product->rating_average ?? 0);
    $reviewCount = (int) ($product->rating_count ?? 0);
    $sold = (int) ($product->sold_count ?? 0);
    $thumbnail = $product->thumbnail_url ?? null;
    $url = $product->storefront_url ?? route('products.show', $product->slug ?? $product->id);
    $isFlash = (bool) ($product->is_flash ?? false);
    $isDigital = ($product->product_type ?? null) === 'digital';
    $name = (string) ($product->name ?? 'Produk');
@endphp

<article {{ $attributes->merge(['class' => 'sf-pcard']) }} data-product-id="{{ $product->id ?? '' }}">
    <a href="{{ $url }}" class="sf-pcard__media" aria-label="Lihat {{ $name }}">
        @if ($thumbnail)
            <img src="{{ $thumbnail }}" alt="{{ $name }}" class="sf-pcard__img" loading="lazy" decoding="async" width="440" height="440">
        @else
            <span class="sf-pcard__placeholder" role="img" aria-label="{{ $name }} (tanpa gambar)">
                <x-storefront.icon name="image" :size="30" />
            </span>
        @endif
    </a>

    <div class="sf-pcard__flags">
        @if ($discount > 0)
            <span class="sf-badge sf-badge--solid-danger">-{{ $discount }}%</span>
        @endif
        @if ($isFlash)
            <span class="sf-badge sf-badge--accent"><x-storefront.icon name="flame" :size="11" /> Flash</span>
        @endif
        @if ($isDigital)
            <span class="sf-badge sf-badge--info">Digital</span>
        @endif
        @if (! $inStock)
            <span class="sf-badge sf-badge--neutral">Habis</span>
        @endif
    </div>

    <button
        type="button"
        class="sf-pcard__wish"
        data-sf-wishlist="{{ auth()->check() ? route('wishlist.toggle') : '#' }}"
        data-signed-out="{{ auth()->check() ? '0' : '1' }}"
        aria-pressed="{{ $wishlisted ? 'true' : 'false' }}"
        aria-label="{{ $wishlisted ? 'Hapus '.$name.' dari favorit' : 'Tambah '.$name.' ke favorit' }}"
        title="{{ $wishlisted ? 'Hapus dari favorit' : 'Tambah ke favorit' }}"
    >
        <x-storefront.icon name="heart" :size="16" />
    </button>

    @if ($inStock)
        <div class="sf-pcard__quick">
            <form method="POST" action="{{ route('cart.add') }}" data-sf-add-cart-form data-signed-out="{{ auth()->check() ? '0' : '1' }}">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="sf-btn sf-btn--primary sf-btn--sm sf-btn--block" data-sf-add-cart aria-label="Tambah {{ $name }} ke keranjang">
                    <x-storefront.icon name="cart" :size="15" /> Tambah
                </button>
            </form>
        </div>
    @endif

    <div class="sf-pcard__body">
        @if ($showShop && ($product->shop?->name ?? null))
            <a href="{{ route('shop.show', $product->shop->slug) }}" class="sf-pcard__shop" aria-label="Kunjungi toko {{ $product->shop->name }}">
                <x-storefront.icon name="store" :size="12" />
                <span>{{ $product->shop->name }}</span>
            </a>
        @endif

        <a href="{{ $url }}" class="sf-pcard__title">{{ $name }}</a>

        <x-storefront.price :amount="$price" :compare-at="$discount > 0 ? $was : null" size="md" />

        <div class="sf-row" style="gap:8px">
            @if ($reviewCount > 0)
                <x-storefront.rating :rating="$rating" :count="$reviewCount" :size="12" />
            @else
                <span class="sf-sold">Belum ada ulasan</span>
            @endif
        </div>

        <div class="sf-row sf-row--wrap" style="gap:8px">
            @if (! $inStock)
                <span class="sf-stock sf-stock--out">
                    <x-storefront.icon name="alert-circle" :size="13" /> Stok habis
                </span>
            @elseif ($isLow)
                <span class="sf-stock sf-stock--low">
                    <x-storefront.icon name="alert-circle" :size="13" /> Sisa {{ \App\Support\Currency::number($stock) }}
                </span>
            @endif
            @if ($sold > 0)
                <span class="sf-sold">{{ \App\Support\Currency::number($sold) }} terjual</span>
            @endif
        </div>

        <div class="sf-pcard__actions">
            @auth
                <form method="POST" action="{{ route('compare.add') }}">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <button type="submit" class="sf-btn sf-btn--ghost sf-btn--sm" aria-label="Bandingkan {{ $name }}">
                        <x-storefront.icon name="scale" :size="14" /> Bandingkan
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="sf-btn sf-btn--ghost sf-btn--sm" aria-label="Masuk untuk membandingkan {{ $name }}">
                    <x-storefront.icon name="scale" :size="14" /> Bandingkan
                </a>
            @endauth
            <a href="{{ $url }}" class="sf-btn sf-btn--ghost sf-btn--sm" aria-label="Lihat detail {{ $name }}">
                Detail
            </a>
        </div>
    </div>
</article>
@endif
