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
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Semua Produk</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-catalog-title">
        <div class="sf-container">
            <div class="sf-section-head">
                <div>
                    <h1 class="sf-section-head__title" id="sf-catalog-title" style="font-size:clamp(1.4rem,1.1rem+1.2vw,2rem)">
                        @if ($query->hasTerm())
                            Hasil untuk &ldquo;{{ $query->term }}&rdquo;
                        @else
                            Semua Produk
                        @endif
                    </h1>
                    <p class="sf-muted sf-small sf-mt-0">
                        Telusuri katalog lengkap dari seluruh toko di platform.
                    </p>
                </div>
                <button type="button" class="sf-btn sf-btn--outline sf-btn--sm" data-sf-drawer-open="filters"
                        aria-label="Buka filter produk">
                    <x-storefront.icon name="filter" :size="16" /> Filter
                </button>
            </div>

            <div class="sf-layout">
                <aside class="sf-filters" aria-label="Filter produk">
                    <x-storefront.filters
                        :categories="$categories"
                        :brands="$brands"
                        :shops="$shopFacet"
                        :query="$query"
                        :action="route('products.index')"
                    />
                </aside>

                <div>
                    <x-storefront.sort-bar :result="$result" :query="$query" :base-url="route('products.index')" />

                    @if ($result->total === 0)
                        <x-storefront.empty
                            title="Produk tidak ditemukan"
                            text="Tidak ada produk yang cocok dengan filter ini. Coba longgarkan filter atau gunakan kata kunci lain."
                            :href="route('products.index')"
                            label="Hapus semua filter"
                            icon="box"
                        />
                    @else
                        <div class="sf-products" data-sf-product-grid>
                            @foreach ($products as $product)
                                <x-storefront.product-card :product="$product" />
                            @endforeach
                        </div>
                        <div data-sf-product-grid-loading hidden>
                            <x-storefront.product-grid-skeleton :count="8" />
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
                    :action="route('products.index')"
                />
            </div>
        </div>
    </div>
@endsection
