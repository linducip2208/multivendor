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

        $suggestedCategories = collect($suggestions['categories'] ?? []);
        $suggestedBrands = collect($suggestions['brands'] ?? []);
        $suggestedShops = collect($suggestions['shops'] ?? []);
        $suggestedTerms = collect($suggestions['terms'] ?? []);
        $hasSuggestions = $suggestedCategories->isNotEmpty() || $suggestedBrands->isNotEmpty()
            || $suggestedShops->isNotEmpty() || $suggestedTerms->isNotEmpty();
    @endphp

    <div class="sf-container">
        <x-storefront.breadcrumb :items="$breadcrumbItems" />
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-search-title">
        <div class="sf-container">
            <div class="sf-section-head">
                <div>
                    <h1 class="sf-section-head__title" id="sf-search-title" style="font-size:clamp(1.4rem,1.1rem+1.2vw,2rem)">
                        @if ($query->hasTerm())
                            Hasil pencarian &ldquo;{{ $query->term }}&rdquo;
                        @else
                            Cari Produk
                        @endif
                    </h1>
                    <p class="sf-muted sf-small sf-mt-0">
                        @if ($query->hasTerm())
                            Cocokkan kata kunci, gunakan filter, atau bandingkan pilihan di bawah ini.
                        @else
                            Ketik kata kunci di kolom pencarian untuk menemukan produk yang Anda cari.
                        @endif
                    </p>
                </div>
                <button type="button" class="sf-btn sf-btn--outline sf-btn--sm" data-sf-drawer-open="filters"
                        aria-label="Buka filter hasil pencarian">
                    <x-storefront.icon name="filter" :size="16" /> Filter
                </button>
            </div>

            @if ($query->hasTerm() && $hasSuggestions)
                <div class="sf-panel" style="margin-bottom:22px">
                    <p class="sf-tiny sf-bold sf-muted sf-mb-0" style="text-transform:uppercase;letter-spacing:.08em">Saran cepat</p>
                    <div class="sf-row sf-row--wrap" style="gap:8px;margin-top:10px">
                        @foreach ($suggestedTerms as $term)
                            <a href="{{ $term['url'] ?? route('search', ['q' => $query->term]) }}" class="sf-chip">
                                <x-storefront.icon name="search" :size="13" /> {{ $term['label'] ?? $query->term }}
                            </a>
                        @endforeach
                        @foreach ($suggestedCategories as $category)
                            <a href="{{ $category['url'] ?? route('categories.index') }}" class="sf-chip">
                                <x-storefront.icon name="grid" :size="13" /> {{ $category['name'] ?? '' }}
                            </a>
                        @endforeach
                        @foreach ($suggestedBrands as $brand)
                            <a href="{{ $brand['url'] ?? route('brands.index') }}" class="sf-chip">
                                <x-storefront.icon name="award" :size="13" /> {{ $brand['name'] ?? '' }}
                            </a>
                        @endforeach
                        @foreach ($suggestedShops as $shop)
                            <a href="{{ $shop['url'] ?? route('stores.index') }}" class="sf-chip">
                                <x-storefront.icon name="store" :size="13" /> {{ $shop['name'] ?? '' }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="sf-layout">
                <aside class="sf-filters" aria-label="Filter hasil pencarian">
                    <x-storefront.filters
                        :categories="$categories"
                        :brands="$brands"
                        :shops="$shopFacet"
                        :query="$query"
                        :action="route('search')"
                    />
                </aside>

                <div>
                    <x-storefront.sort-bar
                        :result="$result"
                        :query="$query"
                        :base-url="route('search', $query->hasTerm() ? ['q' => $query->term] : [])"
                        label="produk"
                    />

                    @if ($result->total === 0)
                        <x-storefront.empty
                            title="Tidak ada hasil"
                            text="Periksa ejaan kata kunci, atau coba kata kunci yang lebih umum."
                            :href="route('products.index')"
                            label="Lihat semua produk"
                            icon="search"
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

    <div id="drawer-filters" class="sf-drawer" data-sf-drawer="filters" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Filter hasil pencarian">
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
                    :action="route('search')"
                />
            </div>
        </div>
    </div>
@endsection
