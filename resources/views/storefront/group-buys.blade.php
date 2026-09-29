@extends('layouts.storefront')

@section('content')
    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Group Buy</span>
        </nav>
    </div>

    <section class="sf-section" aria-labelledby="sf-groupbuy-title">
        <div class="sf-container">
            <div class="sf-section-head">
                <div>
                    <h1 class="sf-section-head__title" id="sf-groupbuy-title">Group Buy</h1>
                    <p class="sf-muted sf-small sf-mt-0" style="max-width:60ch">
                        Gabung dengan pembeli lain untuk mendapat harga khusus saat target peserta tercapai.
                    </p>
                </div>
                <a href="{{ route('products.index') }}" class="sf-section-head__link">
                    Semua produk <x-storefront.icon name="arrow-right" :size="16" />
                </a>
            </div>

            @if ($groups->isNotEmpty())
                <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">
                    @foreach ($groups as $group)
                        @php
                            $target = max(1, (int) $group->target_count);
                            $current = (int) $group->current_count;
                            $progress = min(100, (int) round($current / $target * 100));
                            $price = (float) ($group->special_price ?: 0);
                        @endphp
                        <article class="sf-card">
                            <div class="sf-card__body">
                                <a href="{{ $group->product?->storefront_url ?? route('products.index') }}" aria-label="Lihat {{ $group->product?->name ?? 'produk grup' }}">
                                    @if ($group->product?->thumbnail_url)
                                        <img src="{{ $group->product->thumbnail_url }}" alt="{{ $group->product->name ?? 'Produk grup' }}" width="560" height="420" loading="lazy" decoding="async"
                                             style="width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:var(--sf-radius-sm);background:var(--sf-bg-muted)">
                                    @else
                                        <span class="sf-row" style="aspect-ratio:4/3;border-radius:var(--sf-radius-sm);background:var(--sf-bg-muted);justify-content:center;color:var(--sf-text-subtle)">
                                            <x-storefront.icon name="image" :size="30" />
                                        </span>
                                    @endif
                                </a>

                                <h2 class="sf-mb-0" style="font-size:1rem;margin-top:12px">
                                    <a href="{{ $group->product?->storefront_url ?? route('products.index') }}" style="color:var(--sf-text)">
                                        {{ $group->product?->name ?? 'Produk grup' }}
                                    </a>
                                </h2>

                                @if ($group->product?->shop)
                                    <p class="sf-small sf-muted sf-mb-0">
                                        <x-storefront.icon name="store" :size="13" /> {{ $group->product->shop->name }}
                                    </p>
                                @endif

                                <div class="sf-row sf-row--wrap" style="gap:10px;margin-top:10px">
                                    <x-storefront.price
                                        :amount="$price > 0 ? $price : (float) ($group->product?->price ?? 0)"
                                        :compare-at="$price > 0 ? (float) ($group->product?->price ?? 0) : null"
                                    />
                                    @if ((float) $group->discount_percentage > 0)
                                        <span class="sf-badge sf-badge--solid-danger">-{{ \App\Support\Currency::number($group->discount_percentage) }}%</span>
                                    @endif
                                </div>

                                <div style="margin-top:14px">
                                    <div class="sf-row sf-row--between sf-small sf-muted" style="gap:8px;margin-bottom:6px">
                                        <span>{{ \App\Support\Currency::number($current) }} dari {{ \App\Support\Currency::number($target) }} peserta</span>
                                        <span>{{ \App\Support\Currency::number($progress) }}%</span>
                                    </div>
                                    <span class="sf-rating-bar__track" style="display:block" role="img"
                                          aria-label="Partisipasi grup {{ $current }} dari {{ $target }} peserta">
                                        <span class="sf-rating-bar__fill" style="display:block;width:{{ $progress }}%"></span>
                                    </span>
                                </div>

                                @if ($group->end_date)
                                    <p class="sf-small sf-muted sf-row sf-mb-0" style="gap:6px;margin-top:12px">
                                        <x-storefront.icon name="clock" :size="14" /> Berakhir
                                        <time datetime="{{ $group->end_date->toAtomString() }}">{{ $group->end_date->translatedFormat('d M Y H:i') }}</time>
                                    </p>
                                @endif
                            </div>

                            <div class="sf-card__foot">
                                @auth
                                    <form method="POST" action="{{ route('group-buys.join', $group) }}">
                                        @csrf
                                        <button type="submit" class="sf-btn sf-btn--primary sf-btn--block">
                                            <x-storefront.icon name="user" :size="16" /> Gabung group buy
                                        </button>
                                    </form>
                                @else
                                    <a href="{{ route('login') }}" class="sf-btn sf-btn--primary sf-btn--block">Masuk untuk bergabung</a>
                                @endauth
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <x-storefront.empty
                    title="Belum ada group buy aktif"
                    text="Belum ada grup yang sedang berjalan. Silakan cek kembali nanti atau jelajahi katalog kami."
                    :href="route('products.index')"
                    label="Jelajahi katalog"
                    icon="user"
                />
            @endif
        </div>
    </section>
@endsection
