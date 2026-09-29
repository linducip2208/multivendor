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

    <section class="sf-section sf-section--tight" aria-labelledby="sf-shop-title">
        <div class="sf-container">
            <div class="sf-card">
                <div style="height:clamp(120px,20vw,220px);background:var(--sf-bg-muted);position:relative">
                    @if ($shop->banner_url)
                        <img src="{{ $shop->banner_url }}" alt="Banner toko {{ $shop->name }}" width="1280" height="440"
                             loading="eager" fetchpriority="high" decoding="async"
                             style="width:100%;height:100%;object-fit:cover">
                    @endif
                </div>

                <div class="sf-card__body">
                    <div class="sf-row sf-row--wrap" style="gap:16px;align-items:flex-start">
                        @if ($shop->logo_url)
                            <img src="{{ $shop->logo_url }}" alt="Logo {{ $shop->name }}" width="88" height="88" loading="eager"
                                 decoding="async" class="sf-avatar sf-avatar--lg" style="border-radius:var(--sf-radius-sm)">
                        @else
                            <span class="sf-avatar sf-avatar--lg" aria-hidden="true" style="border-radius:var(--sf-radius-sm)">
                                <x-storefront.icon name="store" :size="30" />
                            </span>
                        @endif

                        <div style="min-width:0;flex:1 1 240px">
                            <h1 class="sf-mb-0" id="sf-shop-title" style="font-size:clamp(1.35rem,1.1rem+1vw,1.9rem)">{{ $shop->name }}</h1>
                            <div class="sf-row sf-row--wrap sf-small sf-muted" style="gap:14px;margin-top:6px">
                                @if ((float) $shop->rating_average > 0)
                                    <span class="sf-rating">
                                        <span class="sf-rating__stars" aria-hidden="true">
                                            <x-storefront.icon name="star" :size="13" :stroke="0" style="color:#f59e0b" />
                                        </span>
                                        <span class="sf-bold" style="color:var(--sf-text)">{{ \App\Support\Currency::number($shop->rating_average, 1) }}</span>
                                        <span class="sf-rating__count">({{ \App\Support\Currency::number($shop->rating_count) }})</span>
                                    </span>
                                @endif
                                @if ($shop->city || $shop->province)
                                    <span class="sf-row" style="gap:5px">
                                        <x-storefront.icon name="map-pin" :size="14" />
                                        <span class="sf-truncate">{{ collect([$shop->city, $shop->province])->filter()->implode(', ') }}</span>
                                    </span>
                                @endif
                                @if ($shop->product_count > 0)
                                    <span class="sf-row" style="gap:5px">
                                        <x-storefront.icon name="box" :size="14" />
                                        {{ \App\Support\Currency::number($shop->product_count) }} produk
                                    </span>
                                @endif
                                @if (($shop->sold_count ?? 0) > 0)
                                    <span class="sf-row" style="gap:5px">
                                        <x-storefront.icon name="trending" :size="14" />
                                        {{ \App\Support\Currency::number($shop->sold_count) }} terjual
                                    </span>
                                @endif
                                @if (($shop->followers_count ?? 0) > 0)
                                    <span class="sf-row" style="gap:5px">
                                        <x-storefront.icon name="user" :size="14" />
                                        {{ \App\Support\Currency::number($shop->followers_count) }} pengikut
                                    </span>
                                @endif
                            </div>

                            @if ($shop->vacation_mode)
                                <p class="sf-small sf-mb-0" style="margin-top:8px;color:var(--sf-warning)">
                                    {{ $shop->vacation_message ?: 'Toko sedang libur.' }}
                                </p>
                            @endif
                        </div>

                        <div class="sf-row sf-row--wrap" style="gap:8px">
                            <a href="{{ route('products.index', ['shop' => $shop->id]) }}" class="sf-btn sf-btn--primary sf-btn--sm">
                                Belanja di toko ini
                            </a>
                            <a href="{{ route('tickets.create') }}" class="sf-btn sf-btn--outline sf-btn--sm">
                                <x-storefront.icon name="headset" :size="15" /> Hubungi
                            </a>
                        </div>
                    </div>

                    @if ($shop->description)
                        <p class="sf-small sf-muted sf-mb-0" style="margin-top:18px;max-width:70ch">
                            {{ \Illuminate\Support\Str::limit(strip_tags((string) $shop->description), 420) }}
                        </p>
                    @endif
                </div>

                <nav class="sf-tabs" aria-label="Halaman toko">
                    <span class="sf-tab" role="tab" aria-selected="true">Produk</span>
                    <a class="sf-tab" href="{{ route('page.seller') }}">Tentang penjual</a>
                    <a class="sf-tab" href="{{ route('page.return') }}">Kebijakan retur</a>
                    <a class="sf-tab" href="{{ route('page.faq') }}">FAQ</a>
                </nav>
            </div>
        </div>
    </section>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-shop-products-title">
        <div class="sf-container">
            <h2 class="sf-section-head__title" id="sf-shop-products-title" style="font-size:clamp(1.2rem,1rem+.8vw,1.6rem);margin-bottom:18px">
                Produk dari {{ $shop->name }}
            </h2>

            <div class="sf-layout">
                <aside class="sf-filters" aria-label="Filter produk toko ini">
                    <x-storefront.filters
                        :shops="$shopFacet"
                        :query="$query"
                        :action="route('shop.show', $shop->slug)"
                    />
                </aside>

                <div>
                    <x-storefront.sort-bar :result="$result" :query="$query" :base-url="route('shop.show', $shop->slug)" />

                    @if ($result->total === 0)
                        <x-storefront.empty
                            title="Belum ada produk yang cocok"
                            text="Toko ini belum memiliki produk yang cocok dengan filter Anda, atau seluruh stoknya sedang habis."
                            :href="route('stores.index')"
                            label="Cari toko lain"
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

    <section class="sf-section sf-section--subtle" aria-labelledby="sf-shop-policy-title">
        <div class="sf-container">
            <h2 class="sf-section-head__title" id="sf-shop-policy-title" style="font-size:1.2rem">Kebijakan toko</h2>
            <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr));margin-top:14px">
                <div class="sf-panel">
                    <h3 class="sf-footer__title">Pengiriman</h3>
                    <p class="sf-small sf-muted sf-mb-0">
                        Kurir, layanan, dan ongkos kirim ditentukan oleh penjual. Rincian ongkos kirim dapat
                        Anda cek sebelum membayar pada halaman checkout.
                    </p>
                </div>
                <div class="sf-panel">
                    <h3 class="sf-footer__title">Retur &amp; garansi</h3>
                    <p class="sf-small sf-muted sf-mb-0">
                        Ketentuan retur mengikuti aturan yang ditetapkan penjual untuk toko ini. Ajukan
                        permintaan retur langsung dari halaman pesanan Anda.
                    </p>
                </div>
                <div class="sf-panel">
                    <h3 class="sf-footer__title">Pembayaran</h3>
                    <p class="sf-small sf-muted sf-mb-0">
                        Seluruh pembayaran diproses melalui payment gateway resmi. Dana diteruskan ke
                        penjual setelah pesanan dinyatakan dikirim.
                    </p>
                </div>
            </div>
            <a href="{{ route('page.return') }}" class="sf-btn sf-btn--outline sf-btn--sm" style="margin-top:16px">
                Baca kebijakan retur lengkap
            </a>
        </div>
    </section>
@endsection
