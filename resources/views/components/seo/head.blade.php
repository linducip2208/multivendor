@php
    /**
     * Canonical + meta + Open Graph + Twitter card for any storefront page.
     *
     * Usage:
     *   <x-seo.head
     *       :title="..." :description="..." :image="..."
     *       :canonical="route('products.show', $product)"
     *       :type="'product'" :robots="'index,follow'" />
     */
    $siteName = $whitelabel['appName'] ?? config('app.name');
    $fullTitle = $title ? ($title === $siteName ? $siteName : $title.' — '.$siteName) : $siteName;
    $desc = \Illuminate\Support\Str::limit(strip_tags((string) ($description ?? '')), 300, '…');
    $canonicalUrl = $canonical ?? url()->current();
    $imageUrl = $image ? (\Illuminate\Support\Str::startsWith($image, ['http://', 'https://']) ? $image : url($image)) : null;
    $robots = $robots ?? 'index,follow,max-image-preview:large';
@endphp
<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $desc }}">
<link rel="canonical" href="{{ $canonicalUrl }}">
<meta name="robots" content="{{ $robots }}">

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
@if (($type ?? null) !== 'website')
    <meta property="product:price:amount" content="{{ $price ?? '' }}">
    <meta property="product:price:currency" content="{{ \App\Support\Currency::config()['code'] }}">
@endif

<meta name="twitter:card" content="{{ $imageUrl ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $fullTitle }}">
<meta name="twitter:description" content="{{ $desc }}">
@if ($imageUrl)
    <meta name="twitter:image" content="{{ $imageUrl }}">
@endif
