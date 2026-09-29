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

    <section class="sf-section sf-section--tight" aria-labelledby="sf-category-title">
        <div class="sf-container">
            <div class="sf-row sf-row--between sf-row--wrap" style="gap:20px;align-items:flex-start;margin-bottom:22px">
                <div style="min-width:0;flex:1 1 280px">
                    <h1 class="sf-section-head__title" id="sf-category-title" style="font-size:clamp(1.5rem,1.2rem+1.4vw,2.25rem)">
                        {{ $category->name }}
                    </h1>
                    @if ($category->description)
                        <p class="sf-muted sf-small sf-mb-0" style="max-width:64ch">
                            {{ \Illuminate\Support\Str::limit(strip_tags((string) $category->description), 240) }}
                        </p>
                    @endif
                </div>
                <button type="button" class="sf-btn sf-btn--outline sf-btn--sm" data-sf-drawer-open="filters" aria-label="Buka filter produk">
                    <x-storefront.icon name="filter" :size="16" /> Filter
                </button>
            </div>

            @if ($children->isNotEmpty())
                <div class="sf-row sf-row--wrap" style="gap:8px;margin-bottom:22px">
                    @foreach ($children as $child)
                        <a href="{{ route('categories.show', $child->slug) }}" class="sf-chip">
                            <x-storefront.icon name="grid" :size="13" /> {{ $child->name }}
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="sf-layout">
                <aside class="sf-filters" aria-label="Filter produk kategori ini">
                    <x-storefront.filters
                        :categories="[$category]"
                        :brands="$brands"
                        :shops="$shopFacet"
                        :query="$query"
                        :action="route('categories.show', $category->slug)"
                    />
                </aside>

                <div>
                    <x-storefront.sort-bar
                        :result="$result"
                        :query="$query"
                        :base-url="route('categories.show', $category->slug)"
                    />

                    @if ($result->total === 0)
                        <x-storefront.empty
                            title="Belum ada produk di kategori ini"
                            text="Coba kategori lain atau longgarkan filter pencarian Anda."
                            :href="route('categories.index')"
                            label="Lihat semua kategori"
                            icon="box"
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

    <div id="drawer-filters" class="sf-drawer" data-sf-drawer="filters" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Filter produk kategori ini">
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
                    :categories="[$category]"
                    :brands="$brands"
                    :shops="$shopFacet"
                    :query="$query"
                    :action="route('categories.show', $category->slug)"
                />
            </div>
        </div>
    </div>
@endsection
