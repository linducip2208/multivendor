@extends('layouts.storefront')

@section('content')
    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Feed</span>
        </nav>
    </div>

    <section class="sf-section" aria-labelledby="sf-feed-title">
        <div class="sf-container">
            <div class="sf-section-head">
                <div>
                    <h1 class="sf-section-head__title" id="sf-feed-title">Feed Produk</h1>
                    <p class="sf-muted sf-small sf-mt-0" style="max-width:60ch">
                        Rekomendasi produk yang dibagikan toko dan penjual pilihan.
                    </p>
                </div>
                <a href="{{ route('products.index') }}" class="sf-section-head__link">
                    Semua produk <x-storefront.icon name="arrow-right" :size="16" />
                </a>
            </div>

            @if ($feeds->total() > 0)
                <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">
                    @foreach ($feeds as $feed)
                        <article class="sf-card">
                            @if ($feed->video_url)
                                <video
                                    controls
                                    playsinline
                                    preload="none"
                                    poster="{{ $feed->product?->thumbnail_url }}"
                                    aria-label="Video produk {{ $feed->product?->name ?? '' }}">
                                    <source src="{{ str_starts_with((string) $feed->video_url, 'http') ? $feed->video_url : url('img/'.ltrim((string) $feed->video_url, '/')) }}"
                                            type="video/mp4">
                                    Browser Anda tidak mendukung pemutaran video.
                                </video>
                            @elseif ($feed->product?->thumbnail_url)
                                <img src="{{ $feed->product->thumbnail_url }}" alt="{{ $feed->product->name }}"
                                     loading="lazy" width="560" height="700" decoding="async"
                                     style="width:100%;aspect-ratio:4/5;object-fit:cover;background:var(--sf-bg-muted)">
                            @endif

                            <div class="sf-card__body">
                                @if ($feed->shop)
                                    <p class="sf-small sf-muted sf-mb-0 sf-row" style="gap:6px">
                                        <x-storefront.icon name="store" :size="14" /> {{ $feed->shop->name }}
                                    </p>
                                @endif

                                @if ($feed->caption)
                                    <p class="sf-small sf-clamp-3" style="margin:8px 0 0">{{ $feed->caption }}</p>
                                @endif

                                <div class="sf-row sf-row--wrap sf-tiny sf-muted" style="gap:14px;margin-top:10px">
                                    <span class="sf-row" style="gap:5px">
                                        <x-storefront.icon name="eye" :size="14" /> {{ \App\Support\Currency::number($feed->views ?? 0) }}
                                    </span>
                                    <span class="sf-row" style="gap:5px">
                                        <x-storefront.icon name="heart" :size="14" /> {{ \App\Support\Currency::number($feed->likes ?? 0) }}
                                    </span>
                                </div>
                            </div>

                            @if ($feed->product)
                                @php
                                    $tagged = $feed->product;
                                    $harga = $tagged->getEffectivePrice();
                                    $coret = $tagged->hasActiveSpecialPrice() && (float) $tagged->special_price < (float) $tagged->price
                                        ? (float) $tagged->price : null;
                                    $stok = (int) ($tagged->current_stock ?? 0);
                                @endphp
                                <div class="sf-card__foot">
                                    <a href="{{ $tagged->storefront_url }}" class="sf-row sf-row--between" style="gap:10px">
                                        <span class="sf-clamp-2 sf-small sf-bold" style="color:var(--sf-text);min-width:0">
                                            {{ $tagged->name }}
                                        </span>
                                        <span class="sf-nowrap" style="text-align:right">
                                            <x-storefront.price :amount="$harga" />
                                            @if ($coret)
                                                <s class="sf-small sf-muted" style="display:block">{{ \App\Support\Currency::format($coret) }}</s>
                                            @endif
                                        </span>
                                    </a>
                                    <div class="sf-row sf-row--between sf-row--wrap" style="gap:8px;margin-top:10px">
                                        <span class="sf-badge {{ $stok > 0 ? 'sf-badge--success' : 'sf-badge--warning' }}">
                                            {{ $stok > 0 ? 'Stok '.$stok : 'Stok habis' }}
                                        </span>
                                        <span class="sf-row" style="gap:8px">
                                            <a href="{{ $tagged->storefront_url }}" class="sf-btn sf-btn--outline sf-btn--sm">Lihat</a>
                                            @if ($stok > 0)
                                                <form method="POST" action="{{ route('cart.add') }}" style="margin:0">
                                                    @csrf
                                                    <input type="hidden" name="product_id" value="{{ $tagged->id }}">
                                                    <input type="hidden" name="quantity" value="1">
                                                    <button type="submit" class="sf-btn sf-btn--primary sf-btn--sm">
                                                        <x-storefront.icon name="cart" :size="14" /> Beli
                                                    </button>
                                                </form>
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>

                <x-storefront.pagination :paginator="$feeds" />
            @else
                <x-storefront.empty
                    title="Feed masih kosong"
                    text="Belum ada toko yang membagikan produk ke feed. Katalog produk tetap bisa dijelajahi sekarang."
                    :href="route('products.index')"
                    label="Jelajahi katalog"
                    icon="video"
                />
            @endif
        </div>
    </section>
@endsection
