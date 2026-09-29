@php
    $cartCount = auth()->check() ? \App\Models\Cart::where('customer_id', auth()->id())->count() : 0;
@endphp

<nav class="sf-bottomnav" aria-label="Navigasi cepat">
    <a href="{{ route('home') }}" class="sf-bottomnav__item {{ request()->routeIs('home') ? 'is-active' : '' }}">
        <x-storefront.icon name="home" :size="21" /> <span>Beranda</span>
    </a>
    <a href="{{ route('categories.index') }}" class="sf-bottomnav__item {{ request()->routeIs('categories.*') ? 'is-active' : '' }}">
        <x-storefront.icon name="grid" :size="21" /> <span>Kategori</span>
    </a>
    <a href="{{ route('cart.index') }}" class="sf-bottomnav__item {{ request()->routeIs('cart.*') ? 'is-active' : '' }}">
        <x-storefront.icon name="cart" :size="21" /> <span>Keranjang</span>
        @if ($cartCount > 0)
            <span class="sf-bottomnav__badge" data-cart-count>{{ $cartCount }}</span>
        @endif
    </a>
    <a href="{{ route('wishlist.index') }}" class="sf-bottomnav__item {{ request()->routeIs('wishlist.*') ? 'is-active' : '' }}">
        <x-storefront.icon name="heart" :size="21" /> <span>Favorit</span>
    </a>
    <a href="{{ auth()->check() ? route('account.dashboard') : route('login') }}"
       class="sf-bottomnav__item {{ request()->routeIs('account.*') ? 'is-active' : '' }}">
        <x-storefront.icon name="user" :size="21" /> <span>{{ auth()->check() ? 'Akun' : 'Masuk' }}</span>
    </a>
</nav>
