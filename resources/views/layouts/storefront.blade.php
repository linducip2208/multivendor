<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $whitelabel['brandColor'] ?? '#4F46E5' }}">
    <link rel="icon" type="image/svg+xml" href="{{ $whitelabel['favicon'] ?? asset('favicon.svg') }}">
    <link rel="apple-touch-icon" href="{{ $whitelabel['favicon'] ?? asset('favicon.svg') }}">

    <x-seo.head
        :title="$metaTitle ?? null"
        :description="$metaDescription ?? null"
        :image="$metaImage ?? null"
        :canonical="$canonicalUrl ?? null"
        :type="$ogType ?? null"
        :robots="$metaRobots ?? null"
        :noindex="($metaRobots ?? '') === 'noindex, follow' || request()->routeIs('*.search') || request()->is('search')"
        :product-price="$productPrice ?? null"
        :product="$product ?? null"
    />

    @if (($jsonLd ?? null))
        <x-seo.json-ld :data="$jsonLd" />
    @else
        {{-- Fallback structured data so EVERY public storefront page (home,
             landing, static pages without a controller-built graph) still emits
             a valid Organization + WebSite graph via x-seo components. --}}
        @php
            $sfBrand = $whitelabel['appName'] ?? config('app.name');
            $sfLogo = $whitelabel['logo'] ?? null;
            $sfFallbackLd = [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Organization',
                        '@id' => url('/').'#organization',
                        'name' => $sfBrand,
                        'url' => url('/'),
                    ] + ($sfLogo ? ['logo' => \Illuminate\Support\Str::startsWith($sfLogo, ['http://', 'https://']) ? $sfLogo : url($sfLogo)] : []),
                    [
                        '@type' => 'WebSite',
                        '@id' => url('/').'#website',
                        'url' => url('/'),
                        'name' => $sfBrand,
                        'publisher' => ['@id' => url('/').'#organization'],
                        'inLanguage' => str_replace('-', '_', app()->getLocale()),
                    ],
                ],
            ];
        @endphp
        <x-seo.json-ld :data="$sfFallbackLd" />
    @endif

    {{-- Fonts are bundled via Bunny in vite.config.js (laravel-vite-plugin fonts);
         no external Google Fonts link — avoids a duplicate font double-load. --}}
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link rel="dns-prefetch" href="https://fonts.bunny.net">

    <style>
        :root {
            --sf-brand: {{ $whitelabel['brandColor'] ?? '#4F46E5' }};
            --sf-brand-600: {{ $whitelabel['brandColorDark'] ?? '#4338ca' }};
            --sf-radius: {{ $whitelabel['borderRadius'] ?? 14 }}px;
            --sf-radius-sm: {{ max(6, ((int) ($whitelabel['borderRadius'] ?? 14)) - 4) }}px;
            --sf-radius-lg: {{ ((int) ($whitelabel['borderRadius'] ?? 14)) + 6 }}px;
        }
    </style>

    @vite(['resources/css/storefront.css', 'resources/js/storefront.js'])
    @stack('head')
</head>
<body class="sf sf-has-bottomnav @stack('body-class')">
    <x-storefront.header />

    <main id="sf-main" tabindex="-1">
        @if (session('success'))
            <div class="sf-container sf-mt-md"><x-storefront.alert type="success">{{ session('success') }}</x-storefront.alert></div>
        @endif
        @if (session('error'))
            <div class="sf-container sf-mt-md"><x-storefront.alert type="error">{{ session('error') }}</x-storefront.alert></div>
        @endif
        @if ($errors->any())
            <div class="sf-container sf-mt-md">
                <x-storefront.alert type="error" title="Periksa kembali isian Anda">
                    <ul style="margin:0;padding-left:18px">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </x-storefront.alert>
            </div>
        @endif

        @yield('content')
    </main>

    <x-storefront.footer />
    <x-storefront.bottom-nav />

    @stack('scripts')
</body>
</html>
