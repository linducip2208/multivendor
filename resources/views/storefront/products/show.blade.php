@extends('layouts.storefront')

@section('content')
    @php
        $sanitizer = app(\App\Services\HtmlSanitizer::class);

        $shortHtml = $sanitizer->sanitize((string) ($product->short_description ?: ''));
        $descriptionHtml = $sanitizer->sanitize((string) ($product->description ?: ''));

        $imageList = $product->images;
        if (is_string($imageList)) {
            $imageList = json_decode($imageList, true) ?: [];
        }

        $galleryPaths = collect((array) $imageList)->filter();
        if ($product->thumbnail) {
            $galleryPaths = $galleryPaths->prepend($product->thumbnail);
        }
        $gallery = $galleryPaths
            ->map(fn ($path) => str_starts_with((string) $path, 'http') ? (string) $path : url('img/'.ltrim((string) $path, '/')))
            ->unique()
            ->values();

        $variantGroups = [];
        foreach ($product->variants as $variant) {
            foreach ((array) ($variant->variant_attributes ?: []) as $attribute) {
                $name = is_array($attribute) ? ($attribute['name'] ?? null) : null;
                $value = is_array($attribute) ? ($attribute['value'] ?? null) : null;
                if ($name === null || $value === null) {
                    continue;
                }
                $variantGroups[$name] = array_values(array_unique(array_merge($variantGroups[$name] ?? [], [$value])));
            }
        }

        $currentPrice = (float) $product->getEffectivePrice();
        $basePrice = (float) $product->price;
        $hasDiscount = $basePrice > 0 && $currentPrice < $basePrice;
        $offPercent = $hasDiscount ? (int) round((($basePrice - $currentPrice) / $basePrice) * 100) : 0;
        $stock = (int) $product->current_stock;
        $maxQty = (int) ($product->max_qty ?: max(1, $stock));

        $ratingAverage = (float) $product->rating_average;
        $ratingCount = (int) $product->rating_count;
        $ratingTotal = max(1, (int) collect($ratingBreakdown)->sum());

        $reviews = collect($product->reviews)->filter(fn ($review) => (bool) $review->status);
        $signedIn = auth()->check();
    @endphp

    <div class="sf-container">
        <x-storefront.breadcrumb :items="$breadcrumbItems" />
    </div>

    <article class="sf-container sf-section sf-section--tight">
        <div class="sf-pdp">
            <div class="sf-gallery">
                <div class="sf-gallery__thumbs">
                    @foreach ($gallery as $index => $image)
                        <button type="button" class="sf-gallery__thumb" data-sf-gallery-thumb data-src="{{ $image }}"
                                aria-current="{{ $index === 0 ? 'true' : 'false' }}"
                                aria-label="Gambar produk {{ $index + 1 }}">
                            <img src="{{ $image }}" alt="" loading="lazy" width="68" height="68" decoding="async">
                        </button>
                    @endforeach
                </div>

                <div class="sf-gallery__main" @if ($gallery->isNotEmpty()) data-sf-gallery-main tabindex="0" role="button"
                     aria-label="Perbesar gambar produk" @endif>
                    @if ($gallery->isNotEmpty())
                        <img src="{{ $gallery->first() }}" alt="{{ $product->name }}" width="900" height="900"
                             loading="eager" fetchpriority="high" decoding="async">
                        <span class="sf-gallery__zoom-hint" aria-hidden="true">
                            <x-storefront.icon name="zoom-in" :size="13" /> Perbesar
                        </span>
                    @else
                        <span class="sf-row" style="justify-content:center;height:100%;color:var(--sf-text-subtle)">
                            <x-storefront.icon name="image" :size="48" />
                        </span>
                    @endif

                    <div class="sf-pcard__flags" style="top:12px;left:12px">
                        @if ($offPercent > 0)
                            <span class="sf-badge sf-badge--solid-danger">-{{ $offPercent }}%</span>
                        @endif
                        @if ($product->product_type === 'digital')
                            <span class="sf-badge sf-badge--info">Digital</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="sf-stack" style="gap:18px">
                @if ($product->shop)
                    <a href="{{ route('shop.show', $product->shop->slug) }}" class="sf-row sf-small sf-muted" style="gap:6px;width:fit-content">
                        <x-storefront.icon name="store" :size="14" /> {{ $product->shop->name }}
                    </a>
                @endif

                <h1 style="font-size:clamp(1.35rem,1.1rem+1vw,1.9rem);margin:0">{{ $product->name }}</h1>

                @if ($ratingCount > 0)
                    <div class="sf-row sf-row--wrap" style="gap:14px">
                        <x-storefront.rating :rating="$ratingAverage" :count="$ratingCount" />
                        @if ($product->sold_count > 0)
                            <span class="sf-sold">{{ \App\Support\Currency::number($product->sold_count) }} terjual</span>
                        @endif
                        @if ($product->view_count > 0)
                            <span class="sf-sold">{{ \App\Support\Currency::number($product->view_count) }} dilihat</span>
                        @endif
                    </div>
                @elseif ($product->sold_count > 0)
                    <span class="sf-sold">{{ \App\Support\Currency::number($product->sold_count) }} terjual</span>
                @endif

                <div class="sf-panel" style="padding:16px">
                    <div class="sf-price">
                        <span class="sf-price__now" style="font-size:1.7rem" data-sf-variant-price>
                            {{ \App\Support\Currency::format($currentPrice) }}
                        </span>
                        @if ($hasDiscount)
                            <span class="sf-price__was" style="font-size:.95rem">{{ \App\Support\Currency::format($basePrice) }}</span>
                            <span class="sf-price__off">-{{ $offPercent }}%</span>
                        @endif
                    </div>
                    <p class="sf-tiny sf-muted sf-mb-0" style="margin-top:6px">Harga sudah termasuk pajak produk yang ditampilkan.</p>
                </div>

                @if ($shortHtml)
                    <div class="sf-prose sf-small">{!! $shortHtml !!}</div>
                @endif

                @if ($variantGroups !== [])
                    <div data-sf-variants="{{ json_encode($variantPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}">
                        @foreach ($variantGroups as $groupName => $groupValues)
                            <div class="sf-field" style="margin-bottom:14px">
                                <span class="sf-label" id="sf-variant-label-{{ \Illuminate\Support\Str::slug($groupName) }}">{{ $groupName }}</span>
                                <div class="sf-variant" role="group" aria-labelledby="sf-variant-label-{{ \Illuminate\Support\Str::slug($groupName) }}" data-sf-variant-group="{{ $groupName }}">
                                    @foreach ($groupValues as $groupValue)
                                        <button type="button" class="sf-variant__opt" data-sf-variant-opt="{{ $groupValue }}" aria-pressed="false">
                                            {{ $groupValue }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="sf-row sf-row--wrap" style="gap:16px">
                    @if ($product->is_out_of_stock)
                        <span class="sf-stock sf-stock--out" data-sf-variant-stock>
                            <x-storefront.icon name="alert-circle" :size="15" /> Stok habis
                        </span>
                    @elseif ($product->is_low_stock)
                        <span class="sf-stock sf-stock--low" data-sf-variant-stock>
                            <x-storefront.icon name="alert-circle" :size="15" /> Tersisa {{ $stock }} unit
                        </span>
                    @else
                        <span class="sf-stock sf-stock--in" data-sf-variant-stock>
                            <x-storefront.icon name="check-circle" :size="15" /> Stok {{ $stock }}
                        </span>
                    @endif

                    @if ($product->sku)
                        <span class="sf-small sf-muted sf-truncate">SKU: {{ $product->sku }}</span>
                    @endif
                </div>

                <div class="sf-row sf-row--wrap" style="gap:10px">
                    @if ($product->is_out_of_stock)
                        <form method="POST" action="{{ route('restock.request') }}" class="sf-row" style="gap:8px">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <button type="submit" class="sf-btn sf-btn--primary">
                                <x-storefront.icon name="bell" :size="16" /> Kabari saat tersedia
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('cart.add') }}" data-sf-add-cart-form
                              data-signed-out="{{ $signedIn ? '0' : '1' }}" class="sf-row sf-row--wrap" style="gap:10px">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <input type="hidden" name="variant_id" value="" id="sf-variant-id" data-sf-variant-id>

                            <div class="sf-qty" data-sf-qty>
                                <button type="button" class="sf-qty__btn" data-qty-dec aria-label="Kurangi jumlah">&minus;</button>
                                <label class="sf-sr-only" for="sf-qty-input">Jumlah pembelian</label>
                                <input class="sf-qty__input" id="sf-qty-input" type="number" name="quantity" value="1"
                                       min="1" max="{{ $maxQty }}" inputmode="numeric" data-sf-qty-input>
                                <button type="button" class="sf-qty__btn" data-qty-inc aria-label="Tambah jumlah">+</button>
                            </div>

                            <button type="submit" class="sf-btn sf-btn--primary sf-btn--lg" data-sf-add-cart>
                                <x-storefront.icon name="cart" :size="18" /> Tambah ke keranjang
                            </button>
                        </form>
                    @endif

                    <button type="button" class="sf-btn sf-btn--outline" aria-pressed="false" aria-label="Tambah ke favorit"
                            data-sf-wishlist="{{ $signedIn ? route('wishlist.toggle') : '#' }}"
                            data-signed-out="{{ $signedIn ? '0' : '1' }}">
                        <x-storefront.icon name="heart" :size="17" /> <span class="sf-hide-mobile">Favorit</span>
                    </button>

                    @auth
                        <form method="POST" action="{{ route('compare.add') }}">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <button type="submit" class="sf-btn sf-btn--ghost" aria-label="Bandingkan produk ini">
                                <x-storefront.icon name="scale" :size="17" /> <span class="sf-hide-mobile">Bandingkan</span>
                            </button>
                        </form>
                    @endauth
                </div>

                @auth
                    <form method="POST" action="{{ route('alerts.set') }}" class="sf-row sf-row--wrap" style="gap:8px">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <div class="sf-field" style="flex:1 1 160px;min-width:0">
                            <label class="sf-sr-only" for="sf-alert-price">Harga target</label>
                            <input class="sf-input" id="sf-alert-price" type="number" name="target_price" min="0" step="any"
                                   required placeholder="Harga target">
                        </div>
                        <button type="submit" class="sf-btn sf-btn--outline sf-btn--sm">
                            <x-storefront.icon name="bell" :size="15" /> Alert harga
                        </button>
                    </form>
                @endauth

                @if ($product->shop)
                    <x-storefront.seller-card :shop="$product->shop" />
                @endif

                <x-storefront.shipping-estimator :product="$product" :estimate="$shippingEstimate" />
            </div>
        </div>
    </article>

    <section class="sf-container sf-section sf-section--tight" aria-labelledby="sf-pdp-info-title">
        <h2 class="sf-sr-only" id="sf-pdp-info-title">Informasi produk</h2>

        <div data-sf-tabs>
            <div class="sf-tabs" role="tablist" aria-label="Informasi produk">
                <button type="button" class="sf-tab" role="tab" id="tab-desc" aria-controls="panel-desc" aria-selected="true">Deskripsi</button>
                <button type="button" class="sf-tab" role="tab" id="tab-spec" aria-controls="panel-spec" aria-selected="false" tabindex="-1">Spesifikasi</button>
                <button type="button" class="sf-tab" role="tab" id="tab-reviews" aria-controls="panel-reviews" aria-selected="false" tabindex="-1">Ulasan ({{ $reviews->count() }})</button>
                <button type="button" class="sf-tab" role="tab" id="tab-ship" aria-controls="panel-ship" aria-selected="false" tabindex="-1">Pengiriman &amp; Retur</button>
                <button type="button" class="sf-tab" role="tab" id="tab-qa" aria-controls="panel-qa" aria-selected="false" tabindex="-1">Tanya Penjual</button>
            </div>

            <div class="sf-tabpanel" role="tabpanel" id="panel-desc" aria-labelledby="tab-desc" tabindex="0">
                @if ($descriptionHtml)
                    <div class="sf-prose">{!! $descriptionHtml !!}</div>
                @else
                    <p class="sf-muted">Penjual belum menambahkan deskripsi lengkap untuk produk ini.</p>
                @endif
            </div>

            <div class="sf-tabpanel" role="tabpanel" id="panel-spec" aria-labelledby="tab-spec" tabindex="0" hidden>
                <table class="sf-specs">
                    <caption class="sf-sr-only">Spesifikasi produk {{ $product->name }}</caption>
                    <tbody>
                        @if ($product->brand)
                            <tr>
                                <th scope="row">Merek</th>
                                <td><a href="{{ route('brands.show', $product->brand->slug) }}">{{ $product->brand->name }}</a></td>
                            </tr>
                        @endif
                        @if ($product->category)
                            <tr>
                                <th scope="row">Kategori</th>
                                <td><a href="{{ route('categories.show', $product->category->slug) }}">{{ $product->category->name }}</a></td>
                            </tr>
                        @endif
                        @if ($product->sku)
                            <tr><th scope="row">SKU</th><td>{{ $product->sku }}</td></tr>
                        @endif
                        @if ($product->condition)
                            <tr><th scope="row">Kondisi</th><td>{{ ucfirst((string) $product->condition) }}</td></tr>
                        @endif
                        <tr><th scope="row">Satuan</th><td>{{ $product->unit ?: 'pcs' }}</td></tr>
                        @if ($product->weight)
                            <tr><th scope="row">Berat</th><td>{{ \App\Support\Currency::number($product->weight, 2) }} kg</td></tr>
                        @endif
                        <tr><th scope="row">Minimum pembelian</th><td>{{ \App\Support\Currency::number($product->min_qty ?: 1) }}</td></tr>
                        <tr><th scope="row">Maksimum pembelian</th><td>{{ $product->max_qty ? \App\Support\Currency::number($product->max_qty) : 'Tidak dibatasi' }}</td></tr>
                        <tr><th scope="row">Tipe produk</th><td>{{ $product->product_type === 'digital' ? 'Digital' : 'Fisik' }}</td></tr>
                        @if ($product->warranty)
                            <tr><th scope="row">Garansi</th><td>{{ $product->warranty }} {{ $product->warranty_unit }}</td></tr>
                        @endif
                        @foreach ($product->attributes as $attribute)
                            @php
                                $attributeName = $attribute->relationLoaded('attribute') && $attribute->attribute
                                    ? $attribute->attribute->name
                                    : 'Atribut';
                            @endphp
                            <tr><th scope="row">{{ $attributeName }}</th><td>{{ $attribute->value }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="sf-tabpanel" role="tabpanel" id="panel-reviews" aria-labelledby="tab-reviews" tabindex="0" hidden>
                <div class="sf-grid" style="grid-template-columns:minmax(0,260px);gap:28px;align-items:start">
                    <div class="sf-panel">
                        <p class="sf-mb-0" style="font-size:2.4rem;font-weight:800;line-height:1">
                            {{ \App\Support\Currency::number($ratingAverage, 1) }}
                        </p>
                        <x-storefront.rating :rating="$ratingAverage" :show-value="false" :size="16" />
                        <p class="sf-small sf-muted sf-mb-0">{{ \App\Support\Currency::number($ratingCount) }} ulasan</p>

                        <div class="sf-rating-bars" style="margin-top:16px">
                            @for ($star = 5; $star >= 1; $star--)
                                @php $share = round((($ratingBreakdown[$star] ?? 0) / $ratingTotal) * 100); @endphp
                                <div class="sf-rating-bar">
                                    <span class="sf-muted">{{ $star }} ★</span>
                                    <span class="sf-rating-bar__track" role="img" aria-label="{{ $star }} bintang: {{ $ratingBreakdown[$star] ?? 0 }} ulasan">
                                        <span class="sf-rating-bar__fill" style="width:{{ $share }}%"></span>
                                    </span>
                                    <span class="sf-muted sf-text-right">{{ \App\Support\Currency::number($ratingBreakdown[$star] ?? 0) }}</span>
                                </div>
                            @endfor
                        </div>
                    </div>

                    <div>
                        @auth
                            <form method="POST" action="{{ route('reviews.store') }}" class="sf-panel sf-stack" style="margin-bottom:20px">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <p class="sf-mb-0 sf-bold">Tulis ulasan Anda</p>
                                <div class="sf-field">
                                    <label class="sf-label" for="sf-review-rating">Rating</label>
                                    <select class="sf-select" id="sf-review-rating" name="rating" required>
                                        <option value="5">5 — Sangat baik</option>
                                        <option value="4">4 — Baik</option>
                                        <option value="3">3 — Cukup</option>
                                        <option value="2">2 — Kurang</option>
                                        <option value="1">1 — Sangat buruk</option>
                                    </select>
                                </div>
                                <div class="sf-field">
                                    <label class="sf-label" for="sf-review-comment">Ulasan</label>
                                    <textarea class="sf-textarea" id="sf-review-comment" name="comment" maxlength="2000" placeholder="Bagaimana pengalaman Anda dengan produk ini?"></textarea>
                                </div>
                                <button type="submit" class="sf-btn sf-btn--primary sf-btn--sm sf-btn--block">Kirim ulasan</button>
                                <p class="sf-tiny sf-muted sf-mb-0">Ulasan hanya tersedia untuk produk dari pesanan yang sudah diterima.</p>
                            </form>
                        @else
                            <x-storefront.alert type="info">
                                <a href="{{ route('login') }}">Masuk</a> untuk menulis ulasan produk ini.
                            </x-storefront.alert>
                        @endauth

                        @forelse ($reviews as $review)
                            <article class="sf-review">
                                <div class="sf-review__head">
                                    <span class="sf-avatar" aria-hidden="true">
                                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($review->customer?->name ?? 'P', 0, 1)) }}
                                    </span>
                                    <div style="min-width:0">
                                        <p class="sf-bold sf-mb-0" style="font-size:.9rem">{{ $review->customer?->name ?? 'Pelanggan' }}</p>
                                        <p class="sf-tiny sf-muted sf-mb-0">
                                            <time datetime="{{ $review->created_at?->toAtomString() }}">{{ $review->created_at?->translatedFormat('d M Y') }}</time>
                                        </p>
                                    </div>
                                    <span style="margin-left:auto">
                                        <x-storefront.rating :rating="$review->rating" :show-value="false" :size="14" />
                                    </span>
                                </div>
                                @if ($review->comment)
                                    <p class="sf-small sf-mb-0">{{ $review->comment }}</p>
                                @endif
                            </article>
                        @empty
                            <x-storefront.empty
                                title="Belum ada ulasan"
                                text="Belum ada ulasan untuk produk ini. Jadilah yang pertama berbagi pengalaman Anda."
                                icon="star"
                            />
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="sf-tabpanel" role="tabpanel" id="panel-ship" aria-labelledby="tab-ship" tabindex="0" hidden>
                <div class="sf-prose sf-small">
                    <p>Estimasi tiba ditampilkan setelah pembeli menyelesaikan pembayaran. Pengiriman ditangani penuh oleh penjual pada masing-masing pesanan.</p>
                    <p>Nomor resi akan tampil di halaman pesanan segera setelah paket diserahkan ke pihak kurir.</p>
                    <p>
                        <a href="{{ route('page.return') }}">Kebijakan pengiriman dan retur</a>
                        berlaku sesuai ketentuan toko penjual.
                    </p>
                </div>
            </div>

            <div class="sf-tabpanel" role="tabpanel" id="panel-qa" aria-labelledby="tab-qa" tabindex="0" hidden>
                <div class="sf-panel sf-stack" style="max-width:620px">
                    <p class="sf-mb-0">Punya pertanyaan soal produk ini? Hubungi penjual langsung dari halaman tokonya.</p>
                    @if ($product->shop)
                        <div class="sf-row sf-row--wrap" style="gap:10px">
                            <a href="{{ route('shop.show', $product->shop->slug) }}" class="sf-btn sf-btn--primary sf-btn--sm">
                                <x-storefront.icon name="store" :size="15" /> Buka halaman {{ $product->shop->name }}
                            </a>
                            @auth
                                <a href="{{ route('tickets.create') }}" class="sf-btn sf-btn--outline sf-btn--sm">
                                    <x-storefront.icon name="headset" :size="15" /> Buka tiket dukungan
                                </a>
                            @endauth
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    @if ($boughtTogether->isNotEmpty())
        <section class="sf-section sf-section--subtle" aria-labelledby="sf-bought-title">
            <div class="sf-container">
                <div class="sf-section-head">
                    <h2 class="sf-section-head__title" id="sf-bought-title">Sering dibeli bersamaan</h2>
                </div>
                <div class="sf-products">
                    @foreach ($boughtTogether as $item)
                        <x-storefront.product-card :product="$item" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($similar->isNotEmpty())
        <section class="sf-section" aria-labelledby="sf-similar-title">
            <div class="sf-container">
                <div class="sf-section-head">
                    <h2 class="sf-section-head__title" id="sf-similar-title">Produk serupa</h2>
                    <a href="{{ route('products.index') }}" class="sf-section-head__link">
                        Semua produk <x-storefront.icon name="arrow-right" :size="16" />
                    </a>
                </div>
                <div class="sf-products">
                    @foreach ($similar as $item)
                        <x-storefront.product-card :product="$item" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($related->isNotEmpty())
        <section class="sf-section sf-section--subtle" aria-labelledby="sf-related-title">
            <div class="sf-container">
                <div class="sf-section-head">
                    <h2 class="sf-section-head__title" id="sf-related-title">Produk terkait</h2>
                    <a href="{{ $product->category ? route('categories.show', $product->category->slug) : route('products.index') }}" class="sf-section-head__link">
                        Lihat kategori <x-storefront.icon name="arrow-right" :size="16" />
                    </a>
                </div>
                <div class="sf-products">
                    @foreach ($related as $item)
                        <x-storefront.product-card :product="$item" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @unless ($product->is_out_of_stock)
        <div class="sf-buybar">
            <div style="min-width:0;flex:1">
                <span class="sf-tiny sf-muted" style="display:block">Harga</span>
                <span class="sf-bold sf-truncate" style="color:var(--sf-brand)">{{ \App\Support\Currency::format($currentPrice) }}</span>
            </div>
            <form method="POST" action="{{ route('cart.add') }}" data-sf-add-cart-form
                  data-signed-out="{{ $signedIn ? '0' : '1' }}" class="sf-row" style="gap:8px">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="variant_id" value="" data-sf-variant-id>
                <div class="sf-qty" data-sf-qty>
                    <button type="button" class="sf-qty__btn" data-qty-dec aria-label="Kurangi jumlah">&minus;</button>
                    <label class="sf-sr-only" for="sf-buybar-qty">Jumlah pembelian</label>
                    <input class="sf-qty__input" id="sf-buybar-qty" type="number" name="quantity" value="1"
                           min="1" max="{{ $maxQty }}" inputmode="numeric" data-sf-qty-input>
                    <button type="button" class="sf-qty__btn" data-qty-inc aria-label="Tambah jumlah">+</button>
                </div>
                <button type="submit" class="sf-btn sf-btn--primary" data-sf-add-cart>
                    <x-storefront.icon name="cart" :size="17" /> <span class="sf-hide-mobile">Keranjang</span>
                </button>
            </form>
        </div>
    @endunless

    <div class="sf-lightbox" hidden>
        <img src="" alt="" width="1200" height="1200">
        <button type="button" class="sf-btn sf-btn--outline sf-lightbox__close" data-lightbox-close aria-label="Tutup tampilan gambar">
            <x-storefront.icon name="close" :size="18" /> Tutup
        </button>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var fields = document.querySelectorAll('[data-sf-variant-id]');
            var button = document.querySelector('[data-sf-add-cart]');
            if (!fields.length || !button || typeof MutationObserver === 'undefined') return;
            new MutationObserver(function () {
                Array.prototype.forEach.call(fields, function (field) {
                    field.value = button.dataset.variantId || '';
                });
            }).observe(button, { attributes: true, attributeFilter: ['data-variant-id'] });
        })();
    </script>
@endpush
