@props([
    'product',
    'wishlisted' => false,
    'showShop' => true,
    'ratio' => null,
])

@php
    $price = (float) ($product->effective_price ?? $product->price);
    $was = (float) $product->price;
    $discount = ($was > 0 && $price < $was) ? (int) round((($was - $price) / $was) * 100) : 0;
    $inStock = (int) ($product->current_stock ?? 0) > 0;
    $rating = (float) ($product->rating_average ?? 0);
    $reviewCount = (int) ($product->rating_count ?? 0);
    $sold = (int) ($product->sold_count ?? 0);
    $thumbnail = $product->thumbnail_url ?? null;
    $url = $product->storefront_url ?? route('products.show', $product->slug ?? $product->id);
    $isFlash = (bool) ($product->is_flash ?? false);
@endphp

<article {{ $attributes->merge(['class' => 'sf-pcard']) }} data-product-id="{{ $product->id ?? '' }}">
    <a href="{{ $url }}" class="sf-pcard__media" tabindex="-1" aria-hidden="true">
        @if ($thumbnail)
            <img src="{{ $thumbnail }}" alt="" class="sf-pcard__img" loading="lazy" decoding="async" width="220" height="220">
        @else
            <span class="sf-pcard__placeholder"><x-storefront.icon name="image" :size="30" /></span>
        @endif
    </a>

    <div class="sf-pcard__flags">
        @if ($discount > 0)
            <span class="sf-badge sf-badge--solid-danger">-{{ $discount }}%</span>
        @endif
        @if ($isFlash)
            <span class="sf-badge sf-badge--accent"><x-storefront.icon name="flame" :size="11" /> Flash</span>
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
        aria-label="{{ $wishlisted ? 'Hapus dari favorit' : 'Tambah ke favorit' }}"
    >
        <x-storefront.icon name="heart" :size="16" />
    </button>

    @if ($inStock)
        <div class="sf-pcard__quick">
            <form method="POST" action="{{ route('cart.add') }}" data-sf-add-cart-form data-signed-out="{{ auth()->check() ? '0' : '1' }}">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="sf-btn sf-btn--primary sf-btn--sm sf-btn--block" data-sf-add-cart>
                    <x-storefront.icon name="cart" :size="15" /> Tambah
                </button>
            </form>
        </div>
    @endif

    <div class="sf-pcard__body">
        @if ($showShop && ($product->shop?->name ?? null))
            <a href="{{ route('shop.show', $product->shop->slug) }}" class="sf-pcard__shop">
                <x-storefront.icon name="store" :size="12" />
                <span>{{ $product->shop->name }}</span>
            </a>
        @endif

        <a href="{{ $url }}" class="sf-pcard__title">{{ $product->name }}</a>

        <div class="sf-price">
            <span class="sf-price__now">{{ \App\Support\Currency::format($price) }}</span>
            @if ($discount > 0)
                <span class="sf-price__was">{{ \App\Support\Currency::format($was) }}</span>
            @endif
        </div>

        <div class="sf-row" style="gap:8px">
            @if ($reviewCount > 0)
                <span class="sf-rating">
                    <span class="sf-rating__stars">
                        @for ($i = 1; $i <= 5; $i++)
                            <x-storefront.icon name="star" :size="12" :stroke="0" :class="$i <= round($rating) ? '' : 'sf-subtle'" style="color:{{ $i <= round($rating) ? '#f59e0b' : 'var(--sf-text-subtle)' }}" />
                        @endfor
                    </span>
                    <span class="sf-rating__count">({{ $reviewCount }})</span>
                </span>
            @else
                <span class="sf-sold">Belum ada ulasan</span>
            @endif
        </div>

        @if ($sold > 0)
            <span class="sf-sold">{{ \App\Support\Currency::number($sold) }} terjual</span>
        @endif
    </div>
</article>
