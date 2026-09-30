@php
    use App\Models\Cart;
    use App\Models\Category;

    $cartCount = auth()->check() ? Cart::where('customer_id', auth()->id())->count() : 0;
    $navCategories = $navCategories ?? (Category::query()
        ->whereNull('parent_id')
        ->where('status', true)
        ->orderBy('sort_order')
        ->limit(10)
        ->get());
    $wishlistCount = auth()->check() ? \App\Models\Wishlist::where('customer_id', auth()->id())->count() : 0;
    $baseQuery = request()->except(['page', 'v', 'sort', 'order', 'ref']);
    // Menu CMS (Admin > Content > Menu > Menu Utama). Kosong = fallback kategori/statis di bawah.
    try {
        $cmsMenuItems = \App\Services\Cms\MenuRenderer::items('main');
    } catch (\Throwable) {
        $cmsMenuItems = [];
    }
@endphp

<a href="#sf-main" class="sf-skip-link">Lompat ke konten utama</a>

<header class="sf-header">
    {{-- Announcement / utility bar --}}
    @php($announcement = \App\Models\SystemSetting::get('storefront_announcement'))
    <div class="sf-header__topbar">
        <div class="sf-container">
            <span class="sf-truncate">{{ $announcement ?: 'Gratis ongkir untuk pembelian pertama di atas '.\App\Support\Currency::format((float) \App\Models\SystemSetting::get('free_shipping_threshold', 150000)) }}</span>
            <span class="sf-row" style="gap:14px">
                <a href="{{ route('track-order') }}" class="sf-hide-mobile"><x-storefront.icon name="package" :size="13" /> Lacak Pesanan</a>
                <a href="{{ route('tickets.index') }}" class="sf-hide-mobile"><x-storefront.icon name="headset" :size="13" /> Bantuan</a>
                {{-- Language switcher ID/EN (pakai lang files existing) --}}
                <span class="sf-row" role="group" aria-label="{{ __('Language') }}" style="gap:6px">
                    <x-storefront.icon name="globe" :size="13" />
                    <a href="{{ request()->fullUrlWithQuery(['lang' => 'id']) }}" hreflang="id" lang="id"
                       @if (app()->getLocale() === 'id') aria-current="true" style="font-weight:700" @endif>ID</a>
                    <span aria-hidden="true">|</span>
                    <a href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}" hreflang="en" lang="en"
                       @if (app()->getLocale() === 'en') aria-current="true" style="font-weight:700" @endif>EN</a>
                </span>
                @if (! auth()->check())
                    <a href="{{ route('vendor.login') }}">Jual di sini</a>
                @endif
            </span>
        </div>
    </div>

    {{-- Main row --}}
    <div class="sf-header__main sf-container">
        <button type="button" class="sf-iconbtn sf-burger" data-sf-drawer-open="nav" aria-label="Buka menu">
            <x-storefront.icon name="menu" :size="22" />
        </button>

        <a href="{{ route('home') }}" class="sf-logo" aria-label="{{ $whitelabel['appName'] ?? config('app.name') }} — beranda">
            @if ($whitelabel['logo'] ?? null)
                <img src="{{ $whitelabel['logo'] }}" alt="{{ $whitelabel['appName'] ?? config('app.name') }}" width="150" height="38">
            @else
                <span class="sf-logo__mark"><x-storefront.icon name="store" :size="20" /></span>
                <span>{{ $whitelabel['appName'] ?? config('app.name') }}</span>
            @endif
        </a>

        {{-- Search with autocomplete --}}
        <div class="sf-search" data-sf-search @class(['is-filled' => request('q') || request('search')])>
            <form class="sf-search__form" action="{{ route('search') }}" method="GET" role="search">
                <label for="sf-search-input" class="sf-sr-only">Cari produk, merek, atau toko</label>
                <input
                    id="sf-search-input"
                    class="sf-search__input"
                    type="search"
                    name="q"
                    value="{{ request('q', request('search')) }}"
                    placeholder="Cari produk, merek, atau toko…"
                    autocomplete="off"
                    role="combobox"
                    aria-expanded="false"
                    aria-controls="sf-search-panel"
                    aria-autocomplete="list"
                    data-sf-search-input
                >
                <button type="button" class="sf-search__clear" data-sf-search-clear aria-label="Hapus pencarian">
                    <x-storefront.icon name="close" :size="16" />
                </button>
                <button type="submit" class="sf-search__submit" aria-label="Cari">
                    <x-storefront.icon name="search" :size="19" />
                </button>
            </form>
            <div id="sf-search-panel" class="sf-suggest" data-sf-search-panel hidden></div>
        </div>

        <div class="sf-headeractions">
            <a href="{{ route('deals') }}" class="sf-iconbtn" title="Promo & Deals">
                <x-storefront.icon name="tag" :size="20" />
                <span class="sf-iconbtn__label">Promo</span>
            </a>

            <a href="{{ route('wishlist.index') }}" class="sf-iconbtn" title="Favorit">
                <x-storefront.icon name="heart" :size="20" />
                <span class="sf-iconbtn__label">Favorit</span>
                @if ($wishlistCount > 0)
                    <span class="sf-iconbtn__badge" data-cart-count>{{ $wishlistCount }}</span>
                @endif
            </a>

            <a href="{{ route('cart.index') }}" class="sf-iconbtn" title="Keranjang">
                <x-storefront.icon name="cart" :size="20" />
                <span class="sf-iconbtn__label">Keranjang</span>
                <span class="sf-iconbtn__badge" data-cart-count @if ($cartCount === 0) hidden @endif>{{ $cartCount }}</span>
            </a>

            @auth
                <div class="sf-row" style="position:relative">
                    <button type="button" class="sf-iconbtn" data-sf-drawer-open="account" aria-label="Akun saya" style="padding-inline:6px">
                        <x-storefront.icon name="user" :size="20" />
                    </button>
                </div>
            @else
                <a href="{{ route('login') }}" class="sf-btn sf-btn--primary sf-btn--sm">Masuk</a>
            @endauth

            <button type="button" class="sf-iconbtn sf-hide-mobile" data-sf-theme-toggle aria-label="Ganti tema">
                <x-storefront.icon name="moon" :size="19" />
            </button>
        </div>
    </div>

    {{-- Menu CMS kustom (dropdown 1 level: hover di desktop, drawer di mobile) --}}
    @if ($cmsMenuItems !== [])
        <nav class="sf-menubar" aria-label="Menu">
            <div class="sf-container">
                <ul class="sf-menubar__list">
                    @foreach ($cmsMenuItems as $item)
                        <li class="sf-menubar__item{{ $item['children'] !== [] ? ' has-children' : '' }}">
                            <a href="{{ $item['url'] }}"
                               class="sf-menubar__link{{ $item['active'] ? ' is-active' : '' }}"
                               @if ($item['active']) aria-current="page" @endif
                               @if ($item['target'] === '_blank') target="_blank" rel="noopener noreferrer" @endif>{{ $item['label'] }}</a>
                            @if ($item['children'] !== [])
                                <ul class="sf-menubar__submenu">
                                    @foreach ($item['children'] as $child)
                                        <li>
                                            <a href="{{ $child['url'] }}"
                                               class="sf-menubar__sublink{{ $child['active'] ? ' is-active' : '' }}"
                                               @if ($child['active']) aria-current="page" @endif
                                               @if ($child['target'] === '_blank') target="_blank" rel="noopener noreferrer" @endif>{{ $child['label'] }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </nav>
    @endif

    {{-- Category navigation bar (fallback bila menu CMS kosong) --}}
    <nav class="sf-catbar" aria-label="Kategori">
        <div class="sf-container">
            <ul class="sf-catbar__list">
                @foreach ($navCategories as $category)
                    <li>
                        <a href="{{ route('categories.show', $category->slug) }}"
                           class="sf-catbar__link @if (request()->routeIs('categories.show') && request()->route('slug') === $category->slug) is-active @endif">
                            <x-storefront.icon name="grid" :size="15" />
                            {{ $category->name }}
                        </a>
                    </li>
                @endforeach
                <li><a href="{{ route('stores.index') }}" class="sf-catbar__link"><x-storefront.icon name="store" :size="15" /> Semua Toko</a></li>
                <li><a href="{{ route('best-sellers') }}" class="sf-catbar__link"><x-storefront.icon name="trending" :size="15" /> Terlaris</a></li>
                <li><a href="{{ route('new-arrivals') }}" class="sf-catbar__link"><x-storefront.icon name="sparkles" :size="15" /> Terbaru</a></li>
                @if (\App\Support\Feature::enabled(\App\Enums\PlatformFeature::Psoe))
                    <li><a href="{{ url('/source-code') }}" class="sf-catbar__link">Source Code</a></li>
                @endif
            </ul>
        </div>
    </nav>
</header>

{{-- Mobile navigation drawer --}}
<div id="drawer-nav" class="sf-drawer" data-sf-drawer="nav" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Menu navigasi">
    <div class="sf-drawer__scrim" data-sf-drawer-close="nav"></div>
    <div class="sf-drawer__panel" data-sf-drawer-panel>
        <div class="sf-drawer__head">
            <span class="sf-logo">
                <span class="sf-logo__mark"><x-storefront.icon name="store" :size="18" /></span>
                <span>{{ $whitelabel['appName'] ?? config('app.name') }}</span>
            </span>
            <button type="button" class="sf-iconbtn" data-sf-drawer-close="nav" aria-label="Tutup menu">
                <x-storefront.icon name="close" :size="20" />
            </button>
        </div>
        <div class="sf-drawer__body sf-stack">
            <form action="{{ route('search') }}" method="GET" role="search">
                <label for="sf-drawer-search" class="sf-sr-only">Cari produk</label>
                <div class="sf-input-group">
                    <input id="sf-drawer-search" class="sf-input" type="search" name="q" value="{{ request('q') }}" placeholder="Cari produk…" style="border-radius:var(--sf-radius-sm)">
                    <button class="sf-btn sf-btn--primary" type="submit" aria-label="Cari"><x-storefront.icon name="search" :size="17" /></button>
                </div>
            </form>

            @auth
                <a href="{{ route('account.dashboard') }}" class="sf-drawer__link"><x-storefront.icon name="user" :size="18" /> Akun Saya</a>
                <a href="{{ route('orders.index') }}" class="sf-drawer__link"><x-storefront.icon name="package" :size="18" /> Pesanan Saya</a>
            @endauth

            <hr class="sf-divider" style="margin-block:8px">

            <span class="sf-tiny sf-bold sf-muted" style="text-transform:uppercase;letter-spacing:.08em">Kategori</span>
            @foreach ($navCategories as $category)
                <a href="{{ route('categories.show', $category->slug) }}" class="sf-drawer__link">
                    <x-storefront.icon name="grid" :size="16" /> {{ $category->name }}
                </a>
            @endforeach

            @if ($cmsMenuItems !== [])
                <hr class="sf-divider" style="margin-block:8px">

                <span class="sf-tiny sf-bold sf-muted" style="text-transform:uppercase;letter-spacing:.08em">Menu</span>
                @foreach ($cmsMenuItems as $item)
                    @if ($item['children'] !== [])
                        <details class="sf-drawer__group">
                            <summary class="sf-drawer__link" style="cursor:pointer;list-style:none">
                                {{ $item['label'] }}
                            </summary>
                            <div style="padding-inline-start:28px;display:grid;gap:2px">
                                <a href="{{ $item['url'] }}" class="sf-drawer__link">{{ $item['label'] }} — Semua</a>
                                @foreach ($item['children'] as $child)
                                    <a href="{{ $child['url'] }}" class="sf-drawer__link">{{ $child['label'] }}</a>
                                @endforeach
                            </div>
                        </details>
                    @else
                        <a href="{{ $item['url'] }}" class="sf-drawer__link" @if ($item['target'] === '_blank') target="_blank" rel="noopener noreferrer" @endif>{{ $item['label'] }}</a>
                    @endif
                @endforeach
            @endif

            <hr class="sf-divider" style="margin-block:8px">

            <a href="{{ route('stores.index') }}" class="sf-drawer__link"><x-storefront.icon name="store" :size="18" /> Semua Toko</a>
            <a href="{{ route('deals') }}" class="sf-drawer__link"><x-storefront.icon name="percent" :size="18" /> Promo & Deals</a>
            <a href="{{ route('flash-sale') }}" class="sf-drawer__link"><x-storefront.icon name="flame" :size="18" /> Flash Sale</a>
            <a href="{{ route('best-sellers') }}" class="sf-drawer__link"><x-storefront.icon name="trending" :size="18" /> Terlaris</a>
            <a href="{{ route('new-arrivals') }}" class="sf-drawer__link"><x-storefront.icon name="sparkles" :size="18" /> Produk Terbaru</a>
            <a href="{{ route('track-order') }}" class="sf-drawer__link"><x-storefront.icon name="truck" :size="18" /> Lacak Pesanan</a>
            <a href="{{ route('tickets.index') }}" class="sf-drawer__link"><x-storefront.icon name="headset" :size="18" /> Bantuan & Tiket</a>
            <a href="{{ route('blog.index') }}" class="sf-drawer__link"><x-storefront.icon name="book" :size="18" /> Blog</a>
            <a href="{{ route('docs') }}" class="sf-drawer__link"><x-storefront.icon name="info" :size="18" /> Panduan Belanja</a>

            <hr class="sf-divider" style="margin-block:8px">

            <div class="sf-row">
                <button type="button" class="sf-btn sf-btn--outline sf-btn--sm" data-sf-theme-toggle>
                    <x-storefront.icon name="moon" :size="15" /> Tema
                </button>
                <span class="sf-row" role="group" aria-label="{{ __('Language') }}" style="gap:6px">
                    <a href="{{ request()->fullUrlWithQuery(['lang' => 'id']) }}" class="sf-btn sf-btn--ghost sf-btn--sm" hreflang="id" lang="id">ID</a>
                    <a href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}" class="sf-btn sf-btn--ghost sf-btn--sm" hreflang="en" lang="en">EN</a>
                </span>
                @auth
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="sf-btn sf-btn--ghost sf-btn--sm">
                            <x-storefront.icon name="logout" :size="15" /> Keluar
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="sf-btn sf-btn--primary sf-btn--sm">Masuk</a>
                    <a href="{{ route('register') }}" class="sf-btn sf-btn--outline sf-btn--sm">Daftar</a>
@endauth

<style>
.sf-menubar { border-top: 1px solid var(--sf-border, #eef0f4); background: var(--sf-surface, #fff); }
.sf-menubar__list { display: flex; flex-wrap: wrap; gap: 2px; list-style: none; margin: 0; padding: 6px 0; }
.sf-menubar__item { position: relative; }
.sf-menubar__link { display: inline-block; padding: 8px 12px; border-radius: 8px; font-weight: 600; font-size: 14px; color: inherit; text-decoration: none; }
.sf-menubar__link:hover, .sf-menubar__link.is-active { background: var(--sf-muted, #f3f4f6); }
.sf-menubar__submenu { display: none; position: absolute; top: 100%; left: 0; z-index: 60; min-width: 200px; list-style: none; margin: 0; padding: 6px; border-radius: 12px; background: var(--sf-surface, #fff); box-shadow: 0 12px 32px rgba(0,0,0,.14); }
.sf-menubar__item.has-children:hover > .sf-menubar__submenu, .sf-menubar__item.has-children:focus-within > .sf-menubar__submenu { display: block; }
.sf-menubar__sublink { display: block; padding: 8px 12px; border-radius: 8px; font-size: 14px; color: inherit; text-decoration: none; }
.sf-menubar__sublink:hover, .sf-menubar__sublink.is-active { background: var(--sf-muted, #f3f4f6); }
</style>
            </div>
        </div>
    </div>
</div>

{{-- Account drawer --}}
@auth
    <div id="drawer-account" class="sf-drawer" data-sf-drawer="account" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Akun saya">
        <div class="sf-drawer__scrim" data-sf-drawer-close="account"></div>
        <div class="sf-drawer__panel" data-sf-drawer-panel>
            <div class="sf-drawer__head">
                <span class="sf-row">
                    <span class="sf-avatar">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->name, 0, 1)) }}</span>
                    <span>
                        <span class="sf-bold" style="display:block">{{ auth()->user()->name }}</span>
                        <span class="sf-tiny sf-muted">{{ auth()->user()->email }}</span>
                    </span>
                </span>
                <button type="button" class="sf-iconbtn" data-sf-drawer-close="account" aria-label="Tutup">
                    <x-storefront.icon name="close" :size="20" />
                </button>
            </div>
            <div class="sf-drawer__body sf-stack">
                <a href="{{ route('account.dashboard') }}" class="sf-drawer__link"><x-storefront.icon name="home" :size="18" /> Dasbor</a>
                <a href="{{ route('orders.index') }}" class="sf-drawer__link"><x-storefront.icon name="package" :size="18" /> Pesanan Saya</a>
                <a href="{{ route('wishlist.index') }}" class="sf-drawer__link"><x-storefront.icon name="heart" :size="18" /> Favorit</a>
                <a href="{{ route('compare.index') }}" class="sf-drawer__link"><x-storefront.icon name="scale" :size="18" /> Bandingkan</a>
                <a href="{{ route('loyalty.index') }}" class="sf-drawer__link"><x-storefront.icon name="coins" :size="18" /> Poin & Hadiah</a>
                <a href="{{ route('account.wallet') }}" class="sf-drawer__link"><x-storefront.icon name="wallet" :size="18" /> Dompet</a>
                <a href="{{ route('account.addresses') }}" class="sf-drawer__link"><x-storefront.icon name="map-pin" :size="18" /> Alamat</a>
                <a href="{{ route('account.notifications') }}" class="sf-drawer__link"><x-storefront.icon name="bell" :size="18" /> Notifikasi</a>
                <a href="{{ route('account.security') }}" class="sf-drawer__link"><x-storefront.icon name="lock" :size="18" /> Keamanan</a>

                <hr class="sf-divider" style="margin-block:8px">

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="sf-drawer__link" style="width:100%;border:0;background:transparent;cursor:pointer;font:inherit">
                        <x-storefront.icon name="logout" :size="18" /> Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
@endauth
