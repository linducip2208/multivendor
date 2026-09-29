@extends('layouts.storefront')

@section('content')
    @php
        $sectionOf = collect($sections)->keyBy('code');
        $payload = static fn (string $code): array => (array) ($data[$code] ?? []);

        $sectionTitle = static function (string $code, ?string $fallback = null) use ($sectionOf): array {
            $title = $sectionOf[$code]['title'] ?? null;
            $subtitle = $sectionOf[$code]['subtitle'] ?? null;

            return ['title' => $title ?: $fallback, 'subtitle' => $subtitle ?: null];
        };
    @endphp

    @foreach ($sections as $section)
        @php
            $code = $section['code'];
            $payloadData = $payload($code);
            $heading = $sectionTitle($code);
            $headingId = 'sf-home-'.$code;
        @endphp

        @switch($code)
            @case('hero')
                @php $slides = collect($payloadData['slides'] ?? [])->filter(); @endphp
                @if ($slides->isNotEmpty())
                    <section class="sf-hero" aria-label="Promosi utama">
                        <div class="sf-container" style="padding-block:clamp(20px,4vw,44px)">
                            <div class="sf-hero__grid">
                                @foreach ($slides->take(3) as $index => $slide)
                                    @if (! is_object($slide))
                                        @continue
                                    @endif
                                    @php
                                        $image = $slide->image ? url('img/'.ltrim((string) $slide->image, '/')) : null;
                                        $href = $slide->link ?: route('products.index');
                                    @endphp
                                    <div @class([
                                        'sf-hero__panel',
                                        'sf-hero__panel--muted' => $index > 0,
                                    ]) @if ($index > 0) style="min-height:180px;padding:clamp(20px,3vw,32px)" @endif>
                                        @if ($image)
                                            <img src="{{ $image }}" alt="{{ $slide->title ?: 'Banner promosi' }}"
                                                 width="800" height="450"
                                                 loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                                                 fetchpriority="{{ $index === 0 ? 'high' : 'auto' }}"
                                                 decoding="async"
                                                 style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.22">
                                        @endif
                                        <div style="position:relative">
                                            @if ($slide->title)
                                                <p class="sf-hero__eyebrow">{{ $slide->title }}</p>
                                            @endif
                                            @if ($slide->subtitle)
                                                <h2 class="sf-hero__title" @if ($index === 0) id="{{ $headingId }}" @endif>
                                                    {{ $slide->subtitle }}
                                                </h2>
                                            @elseif ($index === 0 && $slide->title)
                                                <h2 class="sf-hero__title" id="{{ $headingId }}">{{ $slide->title }}</h2>
                                            @endif
                                            <div class="sf-hero__cta">
                                                <a href="{{ $href }}" class="sf-btn {{ $index === 0 ? 'sf-btn--accent' : 'sf-btn--primary' }}">
                                                    <x-storefront.icon name="arrow-right" :size="16" /> Lihat sekarang
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif
                @break

            @case('flash_sale')
                @php $deal = $payloadData['deal'] ?? null; @endphp
                @if ($deal && $deal->end_date)
                    <div class="sf-flash">
                        <div class="sf-container">
                            <p class="sf-flash__label sf-mb-0">
                                <x-storefront.icon name="flame" :size="20" />
                                <span class="sf-truncate">{{ $deal->title ?: $heading['title'] }}</span>
                            </p>
                            <x-storefront.countdown :ends-at="$deal->end_date" />
                            <a href="{{ route('flash-sale') }}" class="sf-btn sf-btn--sm" style="background:#fff;color:var(--sf-danger);margin-left:auto">
                                Lihat semua <x-storefront.icon name="arrow-right" :size="14" />
                            </a>
                        </div>
                    </div>
                @endif
                @break

            @case('categories')
            @case('category_merchandising')
                @php $categories = collect($payloadData['categories'] ?? [])->filter(); @endphp
                @if ($categories->isNotEmpty())
                    <section class="sf-section" aria-labelledby="{{ $headingId }}">
                        <div class="sf-container">
                            <div class="sf-section-head">
                                <div>
                                    <h2 class="sf-section-head__title" id="{{ $headingId }}">{{ $heading['title'] }}</h2>
                                    @if ($heading['subtitle'])
                                        <p class="sf-muted sf-small sf-mt-0">{{ $heading['subtitle'] }}</p>
                                    @endif
                                </div>
                                <a href="{{ route('categories.index') }}" class="sf-section-head__link">
                                    Semua kategori <x-storefront.icon name="arrow-right" :size="16" />
                                </a>
                            </div>
                            <x-storefront.category-tiles :categories="$categories" />
                        </div>
                    </section>
                @endif
                @break

            @case('promo_banners')
                @php $slides = collect($payloadData['slides'] ?? [])->filter(); @endphp
                @if ($slides->isNotEmpty())
                    <section class="sf-section sf-section--tight" aria-labelledby="{{ $headingId }}">
                        <div class="sf-container">
                            <h2 class="sf-section-head__title" id="{{ $headingId }}">{{ $heading['title'] }}</h2>
                            <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr))">
                                @foreach ($slides as $slide)
                                    @if (! is_object($slide))
                                        @continue
                                    @endif
                                    @php
                                        $image = $slide->image ? url('img/'.ltrim((string) $slide->image, '/')) : null;
                                        $href = $slide->link ?: route('deals');
                                    @endphp
                                    <a href="{{ $href }}" class="sf-banner">
                                        @if ($image)
                                            <img src="{{ $image }}" alt="{{ $slide->title ?: 'Promo' }}" loading="lazy" width="640" height="360" decoding="async">
                                        @else
                                            <span class="sf-row" style="justify-content:center;height:100%;color:var(--sf-text-muted)">
                                                <x-storefront.icon name="image" :size="32" />
                                            </span>
                                        @endif
                                        @if ($slide->title || $slide->subtitle)
                                            <span class="sf-banner__overlay">
                                                <span class="sf-banner__title">{{ $slide->title }}</span>
                                                @if ($slide->subtitle)
                                                    <span class="sf-small" style="opacity:.9">{{ $slide->subtitle }}</span>
                                                @endif
                                            </span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif
                @break

            @case('deals_of_the_day')
                @php $dotd = $payloadData['deal'] ?? null; $dotdProduct = (is_array($dotd) ? ($dotd['product'] ?? null) : null); $dotdProduct = is_object($dotdProduct) ? $dotdProduct : null; @endphp
                @if ($dotdProduct)
                    <section class="sf-section sf-section--subtle" aria-labelledby="{{ $headingId }}">
                        <div class="sf-container">
                            <div class="sf-panel sf-row sf-row--wrap" style="gap:24px;align-items:center">
                                <div style="flex:1 1 260px;min-width:0">
                                    <h2 class="sf-section-head__title" id="{{ $headingId }}">{{ $heading['title'] }}</h2>
                                    @if ($heading['subtitle'])
                                        <p class="sf-muted sf-small sf-mt-0">{{ $heading['subtitle'] }}</p>
                                    @endif
                                    <a href="{{ $dotdProduct->storefront_url }}" class="sf-row" style="gap:14px;margin-top:8px">
                                        <span style="width:76px;height:76px;border-radius:var(--sf-radius-sm);overflow:hidden;background:var(--sf-bg-muted);flex-shrink:0">
                                            @if ($dotdProduct->thumbnail_url)
                                                <img src="{{ $dotdProduct->thumbnail_url }}" alt="{{ $dotdProduct->name }}" width="152" height="152" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover">
                                            @else
                                                <span class="sf-row" style="justify-content:center;height:100%;color:var(--sf-text-subtle)">
                                                    <x-storefront.icon name="image" :size="24" />
                                                </span>
                                            @endif
                                        </span>
                                        <span style="min-width:0">
                                            <span class="sf-bold sf-clamp-2" style="display:-webkit-box;color:var(--sf-text)">{{ $dotdProduct->name }}</span>
                                            <x-storefront.price :amount="$dotdProduct->getEffectivePrice()" :compare-at="$dotdProduct->price" size="lg" />
                                        </span>
                                    </a>
                                </div>
                                <div class="sf-stack" style="flex:0 0 auto;gap:10px">
                                    @if (! empty($dotd['discount']))
                                        <span class="sf-badge sf-badge--solid-danger" style="font-size:.8rem;padding:6px 12px">
                                            -{{ (int) $dotd['discount'] }}%
                                        </span>
                                    @endif
                                    <a href="{{ $dotdProduct->storefront_url }}" class="sf-btn sf-btn--primary">Beli sekarang</a>
                                </div>
                            </div>
                        </div>
                    </section>
                @endif
                @break

            @case('flash_deals')
            @case('featured')
            @case('best_sellers')
            @case('new_arrivals')
            @case('recommended')
            @case('trending')
            @case('most_demanded')
                @php
                    $products = collect($payloadData['products'] ?? [])->filter();
                    $hrefs = [
                        'flash_deals' => route('flash-sale'),
                        'featured' => route('products.index'),
                        'best_sellers' => route('best-sellers'),
                        'new_arrivals' => route('new-arrivals'),
                        'recommended' => route('products.index'),
                        'trending' => route('best-sellers'),
                        'most_demanded' => route('products.index'),
                    ];
                    $eyebrows = [
                        'flash_deals' => 'Flash',
                        'featured' => 'Pilihan',
                        'best_sellers' => 'Terlaris',
                        'new_arrivals' => 'Baru',
                        'recommended' => 'Untuk Anda',
                        'trending' => 'Populer',
                        'most_demanded' => 'Dicari',
                    ];
                @endphp
                @if ($products->isNotEmpty())
                    <section class="sf-section" aria-labelledby="{{ $headingId }}">
                        <div class="sf-container">
                            <div class="sf-section-head">
                                <div>
                                    <span class="sf-section-head__eyebrow">{{ $eyebrows[$code] ?? $heading['title'] }}</span>
                                    <h2 class="sf-section-head__title" id="{{ $headingId }}">{{ $heading['title'] }}</h2>
                                    @if ($heading['subtitle'])
                                        <p class="sf-muted sf-small sf-mt-0">{{ $heading['subtitle'] }}</p>
                                    @endif
                                </div>
                                <a href="{{ $hrefs[$code] ?? route('products.index') }}" class="sf-section-head__link">
                                    Lihat semua <x-storefront.icon name="arrow-right" :size="16" />
                                </a>
                            </div>
                            <div class="sf-carousel" data-sf-carousel>
                                <div class="sf-scroller" data-sf-carousel-track>
                                    @foreach ($products as $product)
                                        <x-storefront.product-card :product="$product" />
                                    @endforeach
                                </div>
                                <button type="button" class="sf-carousel__nav sf-carousel__nav--prev" data-carousel-prev aria-label="Geser produk ke kiri">
                                    <x-storefront.icon name="chevron-left" :size="18" />
                                </button>
                                <button type="button" class="sf-carousel__nav sf-carousel__nav--next" data-carousel-next aria-label="Geser produk ke kanan">
                                    <x-storefront.icon name="chevron-right" :size="18" />
                                </button>
                            </div>
                        </div>
                    </section>
                @endif
                @break

            @case('top_brands')
                @php $brands = collect($payloadData['brands'] ?? [])->filter(); @endphp
                @if ($brands->isNotEmpty())
                    <section class="sf-section sf-section--subtle" aria-labelledby="{{ $headingId }}">
                        <div class="sf-container">
                            <div class="sf-section-head">
                                <div>
                                    <h2 class="sf-section-head__title" id="{{ $headingId }}">{{ $heading['title'] }}</h2>
                                    @if ($heading['subtitle'])
                                        <p class="sf-muted sf-small sf-mt-0">{{ $heading['subtitle'] }}</p>
                                    @endif
                                </div>
                                <a href="{{ route('brands.index') }}" class="sf-section-head__link">
                                    Semua merek <x-storefront.icon name="arrow-right" :size="16" />
                                </a>
                            </div>
                            <x-storefront.brand-pills :brands="$brands" />
                        </div>
                    </section>
                @endif
                @break

            @case('top_stores')
                @php $shops = collect($payloadData['shops'] ?? [])->filter(); @endphp
                @if ($shops->isNotEmpty())
                    <section class="sf-section" aria-labelledby="{{ $headingId }}">
                        <div class="sf-container">
                            <div class="sf-section-head">
                                <div>
                                    <h2 class="sf-section-head__title" id="{{ $headingId }}">{{ $heading['title'] }}</h2>
                                    @if ($heading['subtitle'])
                                        <p class="sf-muted sf-small sf-mt-0">{{ $heading['subtitle'] }}</p>
                                    @endif
                                </div>
                                <a href="{{ route('stores.index') }}" class="sf-section-head__link">
                                    Semua toko <x-storefront.icon name="arrow-right" :size="16" />
                                </a>
                            </div>
                            <x-storefront.store-cards :shops="$shops" />
                        </div>
                    </section>
                @endif
                @break

            @case('buying_guides')
            @case('blog')
                @php $posts = collect($payloadData['posts'] ?? [])->filter(); @endphp
                @if ($posts->isNotEmpty())
                    <section class="sf-section {{ $code === 'blog' ? 'sf-section--subtle' : '' }}" aria-labelledby="{{ $headingId }}">
                        <div class="sf-container">
                            <div class="sf-section-head">
                                <div>
                                    <h2 class="sf-section-head__title" id="{{ $headingId }}">{{ $heading['title'] }}</h2>
                                    @if ($heading['subtitle'])
                                        <p class="sf-muted sf-small sf-mt-0">{{ $heading['subtitle'] }}</p>
                                    @endif
                                </div>
                                <a href="{{ route('blog.index') }}" class="sf-section-head__link">
                                    Semua artikel <x-storefront.icon name="arrow-right" :size="16" />
                                </a>
                            </div>
                            <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
                                @foreach ($posts as $post)
                                    @if (! is_object($post))
                                        @continue
                                    @endif
                                    @php
                                        $cover = $post->featured_image ? url('img/'.ltrim((string) $post->featured_image, '/')) : null;
                                    @endphp
                                    <article class="sf-card">
                                        <a href="{{ route('blog.show', $post->slug) }}" class="sf-banner" style="border-radius:0">
                                            @if ($cover)
                                                <img src="{{ $cover }}" alt="{{ $post->title }}" loading="lazy" width="480" height="270" decoding="async">
                                            @else
                                                <span class="sf-row" style="justify-content:center;height:100%;color:var(--sf-text-subtle)">
                                                    <x-storefront.icon name="book" :size="28" />
                                                </span>
                                            @endif
                                        </a>
                                        <div class="sf-card__body">
                                            <p class="sf-tiny sf-muted sf-mb-0">
                                                <time datetime="{{ $post->published_at?->toAtomString() }}">{{ $post->published_at?->translatedFormat('d M Y') }}</time>
                                            </p>
                                            <h3 class="sf-clamp-2" style="font-size:1rem;margin:4px 0 0">
                                                <a href="{{ route('blog.show', $post->slug) }}" style="color:var(--sf-text)">{{ $post->title }}</a>
                                            </h3>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif
                @break

            @case('trust')
                @php $trustItems = $payloadData['items'] ?? null; @endphp
                @if (is_array($trustItems) && $trustItems !== [])
                    <section class="sf-section sf-section--tight" aria-labelledby="{{ $headingId }}">
                        <div class="sf-container">
                            <h2 class="sf-section-head__title" id="{{ $headingId }}" style="margin-bottom:18px">{{ $heading['title'] }}</h2>
                            <x-storefront.trust-row :items="$trustItems" />
                        </div>
                    </section>
                @endif
                @break

            @case('newsletter')
                <div class="sf-container sf-section--tight" style="padding-block:clamp(24px,4vw,44px)">
                    <x-storefront.newsletter :enabled="$payloadData['enabled'] ?? null" />
                </div>
                @break
        @endswitch
    @endforeach
@endsection
