@extends('layouts.storefront')

@section('content')
    @php
        $shopFacet = collect($result->facets['shops'] ?? [])
            ->map(fn (array $facet, $id) => (object) [
                'id' => (int) $id,
                'name' => $facet['name'] ?? '',
                'slug' => $facet['slug'] ?? null,
                'count' => $facet['count'] ?? null,
            ])
            ->filter(fn ($shop) => $shop->name !== '')
            ->values();
    @endphp

    <div class="sf-container">
        <x-storefront.breadcrumb :items="$breadcrumbItems" />
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-listing-title">
        <div class="sf-container">
            <div class="sf-section-head">
                <div>
                    <h1 class="sf-section-head__title" id="sf-listing-title" style="font-size:clamp(1.5rem,1.2rem+1.4vw,2.25rem)">{{ $heading }}</h1>
                    <p class="sf-muted sf-small sf-mt-0" style="max-width:60ch">{{ $lede }}</p>
                </div>
                <div class="sf-row sf-row--wrap" style="gap:8px">
                    <a href="{{ route('deals') }}" @class(['sf-btn', 'sf-btn--sm', 'sf-btn--primary' => request()->routeIs('deals'), 'sf-btn--outline' => ! request()->routeIs('deals')])>Promo</a>
                    <a href="{{ route('new-arrivals') }}" @class(['sf-btn', 'sf-btn--sm', 'sf-btn--primary' => request()->routeIs('new-arrivals'), 'sf-btn--outline' => ! request()->routeIs('new-arrivals')])>Terbaru</a>
                    <a href="{{ route('best-sellers') }}" @class(['sf-btn', 'sf-btn--sm', 'sf-btn--primary' => request()->routeIs('best-sellers'), 'sf-btn--outline' => ! request()->routeIs('best-sellers')])>Terlaris</a>
                    <button type="button" class="sf-btn sf-btn--outline sf-btn--sm" data-sf-drawer-open="filters" aria-label="Buka filter produk">
                        <x-storefront.icon name="filter" :size="16" /> Filter
                    </button>
                </div>
            </div>

            <div class="sf-layout">
                <aside class="sf-filters" aria-label="Filter produk">
                    <x-storefront.filters
                        :categories="$categories"
                        :brands="$brands"
                        :shops="$shopFacet"
                        :query="$query"
                        :action="request()->url()"
                    />
                </aside>

                <div>
                    <x-storefront.sort-bar :result="$result" :query="$query" :base-url="request()->url()" />

                    @if ($result->total === 0)
                        <x-storefront.empty
                            title="Belum ada produk di halaman ini"
                            text="Kriteria promo ini sedang kosong. Kembali lagi nanti atau jelajahi katalog lengkap."
                            :href="route('products.index')"
                            label="Jelajahi katalog"
                            icon="percent"
                        />
                    @else
                        <div class="sf-products">
                            @foreach ($products as $product)
                                <x-storefront.product-card :product="$product" />
                            @endforeach
                        </div>

                        <x-storefront.pagination :paginator="$products" />
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div id="drawer-filters" class="sf-drawer" data-sf-drawer="filters" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Filter produk">
        <div class="sf-drawer__scrim" data-sf-drawer-close="filters"></div>
        <div class="sf-drawer__panel" data-sf-drawer-panel>
            <div class="sf-drawer__head">
                <span class="sf-bold">Filter</span>
                <button type="button" class="sf-iconbtn" data-sf-drawer-close="filters" aria-label="Tutup filter">
                    <x-storefront.icon name="close" :size="20" />
                </button>
            </div>
            <div class="sf-drawer__body">
                <x-storefront.filters
                    :categories="$categories"
                    :brands="$brands"
                    :shops="$shopFacet"
                    :query="$query"
                    :action="request()->url()"
                />
            </div>
        </div>
    </div>
@endsection
