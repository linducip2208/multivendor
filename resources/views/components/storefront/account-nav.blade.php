@php
    $current = $current ?? (request()->routeIs('account.*') ? (string) request()->route()?->getName() : '');

    $links = collect([
        ['route' => 'account.dashboard', 'icon' => 'home', 'label' => 'Ringkasan'],
        ['route' => 'orders.index', 'icon' => 'package', 'label' => 'Pesanan Saya'],
        ['route' => 'wishlist.index', 'icon' => 'heart', 'label' => 'Favorit'],
        ['route' => 'account.addresses', 'icon' => 'map-pin', 'label' => 'Alamat'],
        ['route' => 'account.wallet', 'icon' => 'wallet', 'label' => 'Dompet'],
        ['route' => 'loyalty.index', 'icon' => 'coins', 'label' => 'Poin & Hadiah'],
        ['route' => 'account.notifications', 'icon' => 'bell', 'label' => 'Notifikasi'],
        ['route' => 'account.messages', 'icon' => 'mail', 'label' => 'Pesan'],
        ['route' => 'account.reviews', 'icon' => 'star', 'label' => 'Ulasan Saya'],
        ['route' => 'account.preferences', 'icon' => 'settings', 'label' => 'Preferensi'],
        ['route' => 'account.security', 'icon' => 'lock', 'label' => 'Keamanan'],
        ['route' => 'tickets.index', 'icon' => 'headset', 'label' => 'Bantuan'],
    ])->filter(fn ($link) => \Illuminate\Support\Facades\Route::has($link['route']));
@endphp

<nav {{ $attributes->merge(['class' => 'sf-account__nav']) }} aria-label="Menu akun">
    @foreach ($links as $link)
        <a href="{{ route($link['route']) }}"
           class="sf-account__link @if ($current === $link['route']) is-active @endif"
           @if ($current === $link['route']) aria-current="page" @endif>
            <x-storefront.icon :name="$link['icon']" :size="17" />
            {{ $link['label'] }}
        </a>
    @endforeach
</nav>
