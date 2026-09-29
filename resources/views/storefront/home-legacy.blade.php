@extends('layouts.storefront')

@section('content')
    @php
        $features = [
            ['icon' => 'store', 't' => 'Multi Vendor', 'd' => 'Vendor bisa daftar, buka toko, dan kelola produk sendiri. Admin kontrol penuh + approve/reject.'],
            ['icon' => 'box', 't' => 'Manajemen Produk', 'd' => 'Produk dengan varian, stok, diskon, SEO meta. Support produk fisik & digital.'],
            ['icon' => 'cart', 't' => 'Smart Cart & Checkout', 'd' => 'Global cart dari banyak toko dalam 1 checkout. Split order per vendor otomatis.'],
            ['icon' => 'wallet', 't' => 'Payment Gateway', 'd' => '10 gateway preset. Midtrans, Xendit, Tripay, Duitku, OY, iPaymu, Faspay, DOKU, ESIA Pay. BYOK.'],
            ['icon' => 'truck', 't' => 'Shipping System', 'd' => '16 kurir preset. RajaOngkir, JNE, J&T, SiCepat, TIKI, POS, GoSend, GrabExpress, Borzo, Deliveree.'],
            ['icon' => 'sparkles', 't' => 'AI Analytics', 'd' => '10 AI provider: DeepSeek, OpenAI, Groq, Ollama, dll. Analisis produk paling laris + rekomendasi.'],
            ['icon' => 'wallet', 't' => 'Wallet & Payout', 'd' => 'Dompet digital customer + vendor. Komisi otomatis per transaksi. Pencairan dana vendor.'],
            ['icon' => 'ticket', 't' => 'Kupon & Flash Deal', 'd' => 'Kupon diskon (%, Rp, gratis ongkir). Flash deal dengan timer. Penawaran Hari Ini.'],
            ['icon' => 'trending', 't' => 'Laporan & Analitik', 'd' => 'Revenue, sales, product, transaction reports. Top produk table. AI-powered insight.'],
            ['icon' => 'book', 't' => 'Blog & SEO', 'd' => 'Blog untuk konten marketing. Sitemap auto-generate. IndexNow auto-submit. robots.txt.'],
            ['icon' => 'user', 't' => 'Pelanggan', 'd' => 'Data pelanggan lengkap: dompet, alamat, riwayat pesanan. Pelanggan masuk & daftar.'],
            ['icon' => 'bell', 't' => 'Notifikasi', 'd' => 'Push notification ke pelanggan. In-app notification system. Firebase ready.'],
            ['icon' => 'image', 't' => 'Banner', 'd' => 'Kelola banner marketing: hero, sidebar, footer, popup. Atur posisi & urutan.'],
            ['icon' => 'external', 't' => 'Integrasi Dinamis', 'd' => 'Semua provider BYOK. User input API key sendiri. Payment, shipping, AI — key terpisah.'],
            ['icon' => 'shield-check', 't' => 'Keamanan', 'd' => 'API key dienkripsi di database. CSRF protection. Input validation. XSS prevention.'],
            ['icon' => 'coins', 't' => 'Loyalty & Referral', 'd' => 'Poin reward dari belanja + referral. Tukar poin ke wallet. Kode referral unik per user.'],
        ];
        $accountLinks = [
            ['href' => route('admin.login'), 'label' => 'Admin'],
            ['href' => route('vendor.login'), 'label' => 'Penjual'],
        ];
    @endphp

    {{-- Intro / account shortcuts (keeps admin/vendor/login route surface) --}}
    <section class="sf-section sf-section--tight" aria-labelledby="sf-legacy-intro">
        <div class="sf-container">
            <div class="sf-panel sf-row sf-row--wrap sf-row--between" style="gap:14px">
                <div class="sf-row" style="gap:10px;min-width:0">
                    @if ($whitelabel['logo'])
                        <img src="{{ $whitelabel['logo'] }}" alt="{{ $whitelabel['appName'] }}" width="120" height="36" style="max-height:36px;width:auto">
                    @else
                        <span class="sf-logo__mark" aria-hidden="true"><x-storefront.icon name="store" :size="18" /></span>
                        <strong>{{ $whitelabel['appName'] }}</strong>
                    @endif
                </div>
                <div class="sf-row sf-row--wrap" style="gap:8px">
                    @auth
                        @if (auth()->user()->isAdmin())
                            <a class="sf-btn sf-btn--primary sf-btn--sm" href="{{ route('admin.dashboard') }}">
                                <x-storefront.icon name="trending" :size="15" /> Panel Admin
                            </a>
                        @elseif (auth()->user()->isVendor())
                            <a class="sf-btn sf-btn--primary sf-btn--sm" href="{{ route('vendor.dashboard') }}">
                                <x-storefront.icon name="store" :size="15" /> Panel Penjual
                            </a>
                        @else
                            <span class="sf-small sf-muted">{{ auth()->user()->name }}</span>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="sf-btn sf-btn--outline sf-btn--sm" aria-label="Keluar dari akun">
                                    <x-storefront.icon name="logout" :size="15" />
                                </button>
                            </form>
                        @endif
                    @else
                        <a class="sf-btn sf-btn--outline sf-btn--sm" href="{{ route('login') }}">Masuk</a>
                        <a class="sf-btn sf-btn--primary sf-btn--sm" href="{{ route('register') }}">Daftar</a>
                        <a class="sf-btn sf-btn--ghost sf-btn--sm" href="{{ route('admin.login') }}">
                            <x-storefront.icon name="shield-check" :size="15" /> Admin
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </section>

    {{-- Hero --}}
    <section class="sf-hero" aria-labelledby="sf-legacy-intro">
        <div class="sf-container" style="padding-block:clamp(28px,5vw,64px)">
            <div class="sf-hero__grid">
                <div class="sf-hero__panel">
                    <p class="sf-hero__eyebrow">Platform {{ $whitelabel['appName'] }}</p>
                    <h1 class="sf-hero__title" id="sf-legacy-intro">Platform <span>Multivendor</span> #1 di Indonesia</h1>
                    <p class="sf-hero__text">Bangun marketplace yang rapi dan siap tumbuh. Satukan katalog, vendor, checkout, pembayaran, pengiriman, dan settlement dalam satu alur yang terukur.</p>
                    <div class="sf-hero__cta">
                        <a href="{{ route('register') }}" class="sf-btn sf-btn--accent sf-btn--lg">
                            <x-storefront.icon name="rocket" :size="17" /> Mulai Gratis
                        </a>
                        <a href="{{ route('products.index') }}" class="sf-btn sf-btn--lg" style="background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.35)">
                            <x-storefront.icon name="play" :size="17" /> Lihat Demo
                        </a>
                    </div>
                    <ul class="sf-row sf-row--wrap" style="gap:14px;list-style:none;margin:22px 0 0;padding:0">
                        @foreach (['Multi Vendor', 'Payment Gateway', 'Shipping Otomatis', 'Wallet System'] as $point)
                            <li class="sf-row" style="gap:7px;font-size:.88rem">
                                <x-storefront.icon name="check-circle" :size="16" /> <span>{{ $point }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="sf-hero__panel sf-hero__panel--muted" aria-label="Kategori unggulan">
                    <p class="sf-hero__eyebrow">Jelajahi</p>
                    <div class="sf-cats">
                        <span class="sf-cat"><span class="sf-cat__icon"><x-storefront.icon name="phone" :size="22" /></span><span class="sf-cat__name">Gadget</span></span>
                        <span class="sf-cat"><span class="sf-cat__icon"><x-storefront.icon name="tag" :size="22" /></span><span class="sf-cat__name">Fashion</span></span>
                        <span class="sf-cat"><span class="sf-cat__icon"><x-storefront.icon name="home" :size="22" /></span><span class="sf-cat__name">Rumah</span></span>
                    </div>
                    <form method="GET" action="{{ route('search') }}" role="search" aria-label="Cari produk favorit" class="sf-search" data-sf-search style="margin-top:16px;max-width:none">
                        <div class="sf-search__form">
                            <label class="sf-sr-only" for="sf-legacy-q">Cari produk favoritmu</label>
                            <input id="sf-legacy-q" class="sf-search__input" type="search" name="q" placeholder="Cari produk favoritmu…" autocomplete="off" data-sf-search-input>
                            <button type="submit" class="sf-search__submit" aria-label="Cari"><x-storefront.icon name="search" :size="17" /></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section class="sf-section" aria-labelledby="sf-legacy-features">
        <div class="sf-container">
            <div class="sf-section-head">
                <div>
                    <span class="sf-section-head__eyebrow">Satu ruang kendali</span>
                    <h2 class="sf-section-head__title" id="sf-legacy-features">Fitur Lengkap Multivendor</h2>
                    <p class="sf-muted sf-small sf-mt-0">Semua yang Anda butuhkan untuk menjalankan marketplace</p>
                </div>
            </div>
            <div class="sf-grid" style="grid-template-columns:repeat(auto-fill,minmax(230px,1fr))">
                @foreach ($features as $f)
                    <article class="sf-card">
                        <div class="sf-card__body sf-stack" style="gap:10px;text-align:center;align-items:center">
                            <span class="sf-cat__icon" aria-hidden="true"><x-storefront.icon :name="$f['icon']" :size="22" /></span>
                            <h3 style="font-size:.95rem;margin:0">{{ $f['t'] }}</h3>
                            <p class="sf-small sf-muted sf-mb-0">{{ $f['d'] }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Deal of the Day --}}
    @if ($dealOfTheDay && $dealOfTheDay->product)
        <section class="sf-section sf-section--subtle" aria-labelledby="sf-legacy-dotd">
            <div class="sf-container">
                <div class="sf-panel sf-row sf-row--wrap" style="gap:24px;align-items:center">
                    <span style="width:220px;max-width:100%;aspect-ratio:1;border-radius:var(--sf-radius);overflow:hidden;background:var(--sf-bg-muted);flex-shrink:0;display:block">
                        @if ($dealOfTheDay->product->thumbnail)
                            <img src="{{ url('img/'.$dealOfTheDay->product->thumbnail) }}" alt="{{ $dealOfTheDay->product->name }}" width="440" height="440" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover">
                        @else
                            <span class="sf-row" style="justify-content:center;height:100%;color:var(--sf-text-subtle)">
                                <x-storefront.icon name="box" :size="44" />
                            </span>
                        @endif
                    </span>
                    <div style="flex:1 1 260px;min-width:0">
                        <span class="sf-badge sf-badge--solid-danger"><x-storefront.icon name="flame" :size="12" /> Penawaran Hari Ini</span>
                        <h2 class="sf-section-head__title" id="sf-legacy-dotd" style="margin-top:10px">{{ $dealOfTheDay->product->name }}</h2>
                        @if ($dealOfTheDay->product->short_description)
                            <p class="sf-small sf-muted">{{ \Illuminate\Support\Str::limit(strip_tags($dealOfTheDay->product->short_description), 140) }}</p>
                        @endif
                        <x-storefront.price :amount="$dealOfTheDay->product->getEffectivePrice()" :compare-at="$dealOfTheDay->product->price" size="lg" />
                        @if ($dealOfTheDay->discount_type || $dealOfTheDay->discount_value)
                            <p class="sf-small sf-muted sf-mb-0">Hemat {{ $dealOfTheDay->discount_type === 'percentage' ? $dealOfTheDay->discount_value.'%' : \App\Support\Currency::format($dealOfTheDay->discount_value) }}</p>
                        @endif
                        <div style="margin-top:14px">
                            <a href="{{ route('products.show', $dealOfTheDay->product->slug) }}" class="sf-btn sf-btn--primary sf-btn--lg">
                                <x-storefront.icon name="cart" :size="17" /> Beli Sekarang
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Featured Products (unified cards) --}}
    @if ($featuredDeals->count() > 0)
        <section class="sf-section" aria-labelledby="sf-legacy-featured">
            <div class="sf-container">
                <div class="sf-section-head">
                    <div>
                        <span class="sf-section-head__eyebrow">Pilihan</span>
                        <h2 class="sf-section-head__title" id="sf-legacy-featured">Produk Unggulan</h2>
                    </div>
                    <a href="{{ route('products.index') }}" class="sf-section-head__link">Lihat semua <x-storefront.icon name="arrow-right" :size="16" /></a>
                </div>
                <div class="sf-products">
                    @foreach ($featuredDeals as $fp)
                        <x-storefront.product-card :product="$fp" />
                    @endforeach
                </div>
            </div>
        </section>
    @else
        <section class="sf-section" aria-labelledby="sf-legacy-featured">
            <div class="sf-container">
                <x-storefront.empty title="Belum ada produk unggulan" text="Produk unggulan akan tampil di sini setelah kurasi ditambahkan." icon="star" />
            </div>
        </section>
    @endif

    {{-- Flash Deals (unified cards) --}}
    @if ($flashDeals->count() > 0)
        <section class="sf-section sf-section--subtle" aria-labelledby="sf-legacy-flash">
            <div class="sf-container">
                <div class="sf-section-head">
                    <div>
                        <span class="sf-section-head__eyebrow">Flash</span>
                        <h2 class="sf-section-head__title" id="sf-legacy-flash">Flash Deal</h2>
                    </div>
                    <a href="{{ route('flash-sale') }}" class="sf-section-head__link">Semua flash deal <x-storefront.icon name="arrow-right" :size="16" /></a>
                </div>
                @foreach ($flashDeals as $fd)
                    <div style="margin-bottom:28px">
                        <div class="sf-row sf-row--between sf-row--wrap" style="gap:10px;margin-bottom:14px">
                            <h3 style="font-size:1.05rem;margin:0">{{ $fd->title }}</h3>
                            <span class="sf-small sf-muted">s/d <time datetime="{{ $fd->end_date->toAtomString() }}">{{ $fd->end_date->translatedFormat('d M H:i') }}</time></span>
                        </div>
                        <div class="sf-products">
                            @foreach ($fd->products->take(4) as $fp)
                                <x-storefront.product-card :product="$fp" />
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Demo accounts --}}
    <section class="sf-section" aria-labelledby="sf-legacy-demo">
        <div class="sf-container">
            <div class="sf-section-head">
                <div>
                    <span class="sf-section-head__eyebrow">Mulai</span>
                    <h2 class="sf-section-head__title" id="sf-legacy-demo">Coba Demo</h2>
                    <p class="sf-muted sf-small sf-mt-0">Gunakan akun berikut untuk mencoba platform</p>
                </div>
            </div>
            <div class="sf-grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
                <article class="sf-card">
                    <div class="sf-card__body sf-stack" style="gap:10px">
                        <span class="sf-row" style="gap:10px">
                            <span class="sf-cat__icon" aria-hidden="true"><x-storefront.icon name="user-shield" :size="20" /></span>
                            <span><strong>Admin</strong><br><span class="sf-small sf-muted">Panel administrasi</span></span>
                        </span>
                        <p class="sf-small sf-muted sf-mb-0">admin@multivendor.test<br>password</p>
                        <a href="{{ route('admin.login') }}" class="sf-btn sf-btn--outline sf-btn--sm sf-btn--block">Masuk Admin</a>
                    </div>
                </article>
                <article class="sf-card">
                    <div class="sf-card__body sf-stack" style="gap:10px">
                        <span class="sf-row" style="gap:10px">
                            <span class="sf-cat__icon" aria-hidden="true"><x-storefront.icon name="store" :size="20" /></span>
                            <span><strong>Penjual</strong><br><span class="sf-small sf-muted">Panel toko</span></span>
                        </span>
                        <p class="sf-small sf-muted sf-mb-0">vendor@multivendor.test<br>password</p>
                        <a href="{{ route('vendor.login') }}" class="sf-btn sf-btn--outline sf-btn--sm sf-btn--block">Masuk Penjual</a>
                    </div>
                </article>
                <article class="sf-card">
                    <div class="sf-card__body sf-stack" style="gap:10px">
                        <span class="sf-row" style="gap:10px">
                            <span class="sf-cat__icon" aria-hidden="true"><x-storefront.icon name="user" :size="20" /></span>
                            <span><strong>Pelanggan</strong><br><span class="sf-small sf-muted">Belanja online</span></span>
                        </span>
                        <p class="sf-small sf-muted sf-mb-0">customer@multivendor.test<br>password</p>
                        <a href="{{ route('login') }}" class="sf-btn sf-btn--outline sf-btn--sm sf-btn--block">Masuk Pelanggan</a>
                    </div>
                </article>
            </div>
        </div>
    </section>

    {{-- Contact strip (keeps support contact surface) --}}
    <section class="sf-section sf-section--tight" aria-label="Kontak {{ config('app.name') }}">
        <div class="sf-container">
            <div class="sf-panel">
                <h2 style="font-size:1.05rem;margin:0 0 6px">{{ config('app.name') }}</h2>
                <p class="sf-small sf-muted sf-mb-0">Platform multivendor e-commerce lengkap dengan payment gateway, shipping, wallet, dan promo marketing.</p>
                <div class="sf-row sf-row--wrap" style="gap:16px;margin-top:12px">
                    <span class="sf-row sf-small" style="gap:7px"><x-storefront.icon name="mail" :size="15" /> support@multivendor.test</span>
                    <span class="sf-row sf-small" style="gap:7px"><x-storefront.icon name="phone" :size="15" /> +62 812-3456-7890</span>
                    @foreach ($accountLinks as $link)
                        <a href="{{ $link['href'] }}" class="sf-small">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
@endsection
