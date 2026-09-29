@extends('layouts.storefront')

@section('content')
    <div class="sf-container">
        <x-storefront.breadcrumb :items="$breadcrumbItems" />
    </div>

    <section class="sf-section" aria-labelledby="sf-stores-title">
        <div class="sf-container">
            <div class="sf-section-head">
                <div>
                    <h1 class="sf-section-head__title" id="sf-stores-title">Semua Toko</h1>
                    <p class="sf-muted sf-small sf-mt-0" style="max-width:60ch">
                        Temukan penjual terbaik dan belanja langsung dari toko yang Anda percaya.
                    </p>
                </div>
                <a href="{{ route('page.seller') }}" class="sf-section-head__link">
                    Ingin berjualan? <x-storefront.icon name="arrow-right" :size="16" />
                </a>
            </div>

            <form method="GET" action="{{ route('stores.index') }}" class="sf-panel sf-row sf-row--wrap" style="gap:10px;margin-bottom:22px">
                <div class="sf-field" style="flex:2 1 240px;min-width:0">
                    <label class="sf-sr-only" for="sf-store-search">Cari nama toko</label>
                    <input class="sf-input" id="sf-store-search" type="search" name="q" value="{{ request('q') }}"
                           placeholder="Cari nama toko…" autocomplete="off">
                </div>

                @if ($cities->isNotEmpty())
                    <div class="sf-field" style="flex:1 1 200px;min-width:0">
                        <label class="sf-sr-only" for="sf-store-city">Filter kota</label>
                        <select class="sf-select" id="sf-store-city" name="city" onchange="this.form.submit()">
                            <option value="">Semua kota</option>
                            @foreach ($cities as $city)
                                <option value="{{ $city }}" @selected(request('city') === $city)>{{ $city }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <button type="submit" class="sf-btn sf-btn--primary">
                    <x-storefront.icon name="search" :size="16" /> Cari
                </button>

                @if (request()->hasAny(['q', 'city']))
                    <a href="{{ route('stores.index') }}" class="sf-btn sf-btn--ghost">Reset</a>
                @endif
            </form>

            @php
                // Toko terdekat: urutkan berdasar kota/provinsi pelanggan, fallback urutan existing.
                $refCity = trim((string) request('city', ''));
                $refProvince = '';
                try {
                    $defAddr = auth()->check() ? auth()->user()->addresses()->where('is_default', true)->first() : null;
                    $refCity = $refCity !== '' ? $refCity : trim((string) ($defAddr?->city ?? ''));
                    $refProvince = trim((string) ($defAddr?->province ?? ''));
                } catch (\Throwable $e) {
                    $refCity = trim((string) request('city', ''));
                }
                $nearReasons = [];
                $orderedShops = $shops->getCollection();
                try {
                    $nearest = app(\App\Services\Analytics\StockAnalyticsService::class)->nearestShops($refCity ?: null, $refProvince ?: null, 200);
                    $rank = collect($nearest)->pluck('score', 'id');
                    $nearReasons = collect($nearest)->pluck('reason', 'id')->all();
                    if ($rank->isNotEmpty()) {
                        $orderedShops = $orderedShops->sortBy(
                            fn ($s) => [$rank->has($s->id) ? -$rank[$s->id] : 0, $s->name]
                        )->values();
                    }
                } catch (\Throwable $e) {
                    $orderedShops = $shops->getCollection();
                }
            @endphp

            @if ($shops->total() > 0)
                <p class="sf-small sf-muted">
                    <span class="sf-bold" style="color:var(--sf-text)">{{ \App\Support\Currency::number($shops->total()) }}</span>
                    toko tersedia
                    @if ($refCity !== '')
                        · diurutkan terdekat dari <span class="sf-bold" style="color:var(--sf-text)">{{ $refCity }}</span>
                    @endif
                </p>

                <div class="sf-stores" style="margin-top:12px">
                    @foreach ($orderedShops as $shop)
                        @php
                            $shopLat = $shop->latitude !== null ? (float) $shop->latitude : null;
                            $shopLng = $shop->longitude !== null ? (float) $shop->longitude : null;
                            $shopMap = \App\Services\Analytics\AnalyticsService::mapEmbedUrl($shopLat, $shopLng);
                            $shopReason = $nearReasons[$shop->id] ?? null;
                            $shopRadius = $shop->getAttribute('service_radius_km');
                        @endphp
                        <a href="{{ route('shop.show', $shop->slug) }}" class="sf-store">
                            @if ($shop->logo_url)
                                <img src="{{ $shop->logo_url }}" alt="" class="sf-store__logo" loading="lazy" width="52" height="52" decoding="async">
                            @else
                                <span class="sf-store__logo sf-row" style="justify-content:center" aria-hidden="true">
                                    <x-storefront.icon name="store" :size="22" />
                                </span>
                            @endif
                            <span style="min-width:0;flex:1">
                                <span class="sf-store__name sf-clamp-2" style="display:-webkit-box">{{ $shop->name }}</span>
                                <span class="sf-store__meta sf-clamp-2" style="display:-webkit-box">
                                    @if ((float) $shop->rating_average > 0)
                                        {{ \App\Support\Currency::number($shop->rating_average, 1) }} dari 5
                                    @endif
                                    @if ($shop->products_count > 0)
                                        {{ \App\Support\Currency::number($shop->products_count) }} produk
                                    @endif
                                    @if ($shop->city)
                                        {{ $shop->city }}
                                    @endif
                                    @if ($shopReason && $shopReason !== 'Lainnya')
                                        · {{ $shopReason }}
                                    @endif
                                </span>
                                @if ($shopMap)
                                    <details class="sf-small" style="margin-top:6px" onclick="event.stopPropagation()">
                                        <summary class="sf-muted" style="cursor:pointer" onclick="event.stopPropagation()">Lihat peta</summary>
                                        <iframe
                                            src="{{ $shopMap }}"
                                            width="100%" height="180" style="border:0;border-radius:8px;margin-top:6px"
                                            loading="lazy" title="Peta {{ $shop->name }}"></iframe>
                                        @if ($shopRadius)
                                            <span class="sf-muted">Radius layanan ±{{ $shopRadius }} km.</span>
                                        @endif
                                    </details>
                                @endif
                            </span>
                        </a>
                    @endforeach
                </div>

                <x-storefront.pagination :paginator="$shops" />
            @else
                <x-storefront.empty
                    title="Toko tidak ditemukan"
                    text="Coba kata kunci lain atau hapus filter kota untuk melihat lebih banyak toko."
                    :href="route('stores.index')"
                    label="Tampilkan semua toko"
                    icon="store"
                />
            @endif
        </div>
    </section>
@endsection
