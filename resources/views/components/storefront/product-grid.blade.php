@props(['items', 'empty' => null, 'columns' => null])

@if (count($items) > 0)
    <div {{ $attributes->merge(['class' => 'sf-products']) }}>
        @foreach ($items as $product)
            <x-storefront.product-card
                :product="$product"
                :wishlisted="in_array($product->id, session('wishlist_ids', []))"
            />
        @endforeach
    </div>
@else
    <x-storefront.empty
        :title="$empty['title'] ?? 'Belum ada produk'"
        :text="$empty['text'] ?? 'Coba ubah filter atau kembali lagi nanti.'"
        :href="$empty['href'] ?? null"
        :label="$empty['label'] ?? null"
    />
@endif
