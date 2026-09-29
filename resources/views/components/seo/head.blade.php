@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'canonical' => null,
    'type' => 'website',
    'robots' => null,
    'noindex' => false,
    'price' => null,
    'productPrice' => null,
    'product' => null,
])
@php
    /**
     * Canonical + meta + Open Graph + Twitter card for any storefront page.
     *
     * Usage:
     *   <x-seo.head
     *       :title="..." :description="..." :image="..."
     *       :canonical="route('products.show', $product)"
     *       :type="'product'" :robots="'index,follow'"
     *       :product-price="$product->getEffectivePrice()" :product="$product" />
     *
     * Product price meta only renders for product pages with a resolvable
     * numeric amount (explicit :product-price / legacy :price, or derived
     * from :product). Otherwise the tags are omitted — never blank.
     */
    $siteName = $whitelabel['appName'] ?? config('app.name');
    $fullTitle = $title ? ($title === $siteName ? $siteName : $title.' — '.$siteName) : $siteName;
    $fallbackDesc = config('app.seo.default_description', $siteName.' — belanja online multi-vendor: produk original, harga bersaing, pengiriman cepat.');
    $rawDesc = trim((string) ($description ?? ''));
    $desc = \Illuminate\Support\Str::limit(strip_tags($rawDesc !== '' ? $rawDesc : (string) $fallbackDesc), 300, '…');
    $canonicalUrl = $canonical ?? url()->current();
    $rawImage = $image ?? $whitelabel['logo'] ?? null;
    $imageUrl = $rawImage ? (\Illuminate\Support\Str::startsWith($rawImage, ['http://', 'https://']) ? $rawImage : url($rawImage)) : null;
    $robots = $robots ?? 'index,follow,max-image-preview:large';

    // Resolve product price: explicit :product-price wins, then legacy :price,
    // then derive from :product (effective price preferred over base price).
    $resolvedPrice = $productPrice ?? $price ?? null;
    if (($resolvedPrice === null || $resolvedPrice === '') && $product !== null && is_object($product)) {
        try {
            $resolvedPrice = method_exists($product, 'getEffectivePrice')
                ? $product->getEffectivePrice()
                : ($product->effective_price ?? $product->price ?? null);
        } catch (\Throwable) {
            $resolvedPrice = null;
        }
    }
    $priceAmount = is_numeric($resolvedPrice)
        ? number_format((float) $resolvedPrice, \App\Support\Currency::config()['decimals'], '.', '')
        : null;
    $priceCurrency = \App\Support\Currency::config()['code'];
    $isProduct = (($type ?? 'website') === 'product') || $product !== null;
@endphp
<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $desc }}">
<link rel="canonical" href="{{ $canonicalUrl }}">
<meta name="robots" content="{{ $robots }}">
<meta name="author" content="{{ $siteName }}">
<meta name="geo.region" content="{{ config('app.seo.geo_region', 'ID') }}">
<meta name="geo.placename" content="{{ config('app.seo.geo_placename', 'Indonesia') }}">

{{-- Performance: font origin hints (Bunny via vite.config.js). Preconnect in
     <head> avoids an extra DNS+TLS round-trip on first paint; every image in
     storefront views already carries explicit loading/decoding attrs. --}}
<link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
<link rel="dns-prefetch" href="https://fonts.bunny.net">

@if ($noindex)
    <meta name="googlebot" content="noindex, nofollow">
@endif

<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:type" content="{{ $type ?? 'website' }}">
<meta property="og:title" content="{{ $fullTitle }}">
<meta property="og:description" content="{{ $desc }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
@if ($imageUrl)
    <meta property="og:image" content="{{ $imageUrl }}">
    <meta property="og:image:alt" content="{{ $title ?? $siteName }}">
@endif
@if ($isProduct && $priceAmount !== null)
    <meta property="product:price:amount" content="{{ $priceAmount }}">
    <meta property="product:price:currency" content="{{ $priceCurrency }}">
@endif

<meta name="twitter:card" content="{{ $imageUrl ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $fullTitle }}">
<meta name="twitter:description" content="{{ $desc }}">
@if ($imageUrl)
    <meta name="twitter:image" content="{{ $imageUrl }}">
@endif
