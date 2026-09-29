@extends('layouts.storefront')

@section('content')
    @php
        $reviews = $reviews ?? null;
        $rows = $reviews instanceof \Illuminate\Contracts\Pagination\Paginator
            ? collect($reviews->items())
            : collect($reviews ?? []);
        $pendingItems = collect($pendingItems ?? []);
    @endphp

    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <a href="{{ route('account.dashboard') }}">Dashboard</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Ulasan Saya</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-reviews-title">
        <div class="sf-container">
            <h1 class="sf-section-head__title" id="sf-reviews-title">Ulasan Saya</h1>
            <p class="sf-muted sf-small" style="max-width:60ch">
                Ulasan hanya dapat ditulis untuk produk dari pesanan yang sudah diterima.
            </p>

            <div class="sf-account">
                <aside>
                    <x-storefront.account-nav current="account.reviews" />
                </aside>

                <div class="sf-stack" style="gap:20px">
                    @if ($pendingItems->isNotEmpty())
                        <section class="sf-card" aria-labelledby="sf-reviews-pending">
                            <div class="sf-card__body">
                                <h2 class="sf-footer__title" id="sf-reviews-pending">Menunggu ulasan</h2>
                                <div class="sf-stack" style="gap:12px;margin-top:12px">
                                    @foreach ($pendingItems as $item)
                                        <div class="sf-shipbox" style="cursor:default">
                                            <div class="sf-row sf-row--wrap" style="gap:12px;align-items:flex-start">
                                                <span style="width:60px;height:60px;border-radius:var(--sf-radius-sm);overflow:hidden;background:var(--sf-bg-muted);flex-shrink:0">
                                                    @if ($item->product?->thumbnail_url)
                                                        <img src="{{ $item->product->thumbnail_url }}" alt="" width="120" height="120"
                                                             loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover">
                                                    @else
                                                        <span class="sf-row" style="justify-content:center;height:100%;color:var(--sf-text-subtle)">
                                                            <x-storefront.icon name="image" :size="20" />
                                                        </span>
                                                    @endif
                                                </span>

                                                <form method="POST" action="{{ route('reviews.store') }}" class="sf-stack" style="gap:10px;flex:1 1 260px;min-width:0">
                                                    @csrf
                                                    <input type="hidden" name="product_id" value="{{ $item->product_id }}">
                                                    <span class="sf-clamp-2 sf-small sf-bold" style="color:var(--sf-text)">
                                                        {{ $item->product?->name ?? 'Produk' }}
                                                    </span>

                                                    <div class="sf-field">
                                                        <label class="sf-label" for="sf-review-pending-{{ $item->id }}">Rating</label>
                                                        <select class="sf-select" id="sf-review-pending-{{ $item->id }}" name="rating" required>
                                                            <option value="5">5 — Sangat baik</option>
                                                            <option value="4">4 — Baik</option>
                                                            <option value="3">3 — Cukup</option>
                                                            <option value="2">2 — Kurang</option>
                                                            <option value="1">1 — Sangat buruk</option>
                                                        </select>
                                                    </div>

                                                    <div class="sf-field">
                                                        <label class="sf-label" for="sf-review-text-{{ $item->id }}">Ulasan</label>
                                                        <textarea class="sf-textarea" id="sf-review-text-{{ $item->id }}" name="comment" rows="3"
                                                                  maxlength="2000" placeholder="Bagaimana pengalaman Anda?"></textarea>
                                                    </div>

                                                    <div>
                                                        <button type="submit" class="sf-btn sf-btn--primary sf-btn--sm">
                                                            <x-storefront.icon name="star" :size="15" :stroke="0" /> Kirim ulasan
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </section>
                    @endif

                    <section class="sf-card" aria-labelledby="sf-reviews-list">
                        <div class="sf-card__body">
                            <h2 class="sf-footer__title" id="sf-reviews-list">Ulasan yang telah dikirim</h2>

                            @if ($rows->isNotEmpty())
                                <div class="sf-stack" style="gap:12px;margin-top:12px">
                                    @foreach ($rows as $review)
                                        <article class="sf-review">
                                            <div class="sf-review__head">
                                                @if ($review->product?->thumbnail_url)
                                                    <img src="{{ $review->product->thumbnail_url }}" alt="" width="44" height="44"
                                                         loading="lazy" decoding="async"
                                                         style="width:44px;height:44px;border-radius:var(--sf-radius-xs);object-fit:cover">
                                                @endif
                                                <div style="min-width:0">
                                                    <a href="{{ $review->product?->storefront_url ?? route('products.index') }}"
                                                       class="sf-clamp-2 sf-bold" style="color:var(--sf-text);font-size:.9rem">
                                                        {{ $review->product?->name ?? 'Produk' }}
                                                    </a>
                                                    <p class="sf-tiny sf-muted sf-mb-0">
                                                        <time datetime="{{ $review->created_at?->toAtomString() }}">
                                                            {{ $review->created_at?->translatedFormat('d M Y') }}
                                                        </time>
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
                                    @endforeach
                                </div>

                                @if ($reviews instanceof \Illuminate\Contracts\Pagination\Paginator)
                                    <x-storefront.pagination :paginator="$reviews" />
                                @endif
                            @else
                                <x-storefront.empty
                                    title="Belum ada ulasan"
                                    text="Ulasan yang Anda kirim akan tampil di sini beserta produknya."
                                    :href="route('orders.index')"
                                    label="Lihat pesanan saya"
                                    icon="star"
                                />
                            @endif
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </section>
@endsection
