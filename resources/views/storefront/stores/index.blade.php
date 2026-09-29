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

            @if ($shops->total() > 0)
                <p class="sf-small sf-muted">
                    <span class="sf-bold" style="color:var(--sf-text)">{{ \App\Support\Currency::number($shops->total()) }}</span>
                    toko tersedia
                </p>

                <div class="sf-stores" style="margin-top:12px">
                    @foreach ($shops as $shop)
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
                                </span>
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
