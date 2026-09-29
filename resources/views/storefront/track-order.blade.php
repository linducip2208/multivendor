@extends('layouts.storefront')

@section('content')
    @php
        $statusEnum = $order ? \App\Enums\OrderStatus::fromStored($order->order_status) : null;
        $statusBadge = match ($statusEnum?->badge()) {
            'warning' => 'sf-badge--warning',
            'info' => 'sf-badge--info',
            'primary' => 'sf-badge--brand',
            'success' => 'sf-badge--success',
            default => 'sf-badge--neutral',
        };
        $lookedUp = request()->filled('order_number');
    @endphp

    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Lacak Pesanan</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-track-title">
        <div class="sf-container" style="max-width:760px">
            <h1 class="sf-section-head__title" id="sf-track-title">Lacak Pesanan</h1>
            <p class="sf-muted sf-small" style="max-width:56ch">
                Masukkan nomor pesanan yang Anda terima untuk melihat status pengiriman dan riwayat lengkapnya.
            </p>

            <form method="GET" action="{{ route('track-order') }}" class="sf-panel sf-row sf-row--wrap" style="gap:10px;margin:20px 0">
                <div class="sf-field" style="flex:1 1 240px;min-width:0">
                    <label class="sf-sr-only" for="sf-track-input">Nomor pesanan</label>
                    <input class="sf-input" id="sf-track-input" type="text" name="order_number" required
                           value="{{ request('order_number') }}" placeholder="Contoh: ORD-20240101-ABCDE12345"
                           autocomplete="off">
                </div>
                <button type="submit" class="sf-btn sf-btn--primary">
                    <x-storefront.icon name="search" :size="16" /> Lacak
                </button>
            </form>

            @if ($order)
                <article class="sf-card">
                    <div class="sf-card__body">
                        <div class="sf-row sf-row--between sf-row--wrap" style="gap:10px">
                            <div style="min-width:0">
                                <h2 class="sf-mb-0" style="font-size:1.1rem">{{ $order->order_number }}</h2>
                                <p class="sf-small sf-muted sf-mb-0">
                                    {{ $order->shop?->name ?? 'Toko' }}
                                    &middot;
                                    <time datetime="{{ $order->created_at?->toAtomString() }}">{{ $order->created_at?->translatedFormat('d M Y H:i') }}</time>
                                </p>
                            </div>
                            <span class="sf-badge {{ $statusBadge }}">{{ $statusEnum?->label() }}</span>
                        </div>

                        <div class="sf-summary" style="margin-top:16px">
                            <div class="sf-summary__row">
                                <span class="sf-summary__label">Total pesanan</span>
                                <span class="sf-bold">{{ \App\Support\Currency::format($order->total) }}</span>
                            </div>
                            @if ($order->shipping_tracking_id)
                                <div class="sf-summary__row">
                                    <span class="sf-summary__label">Nomor resi</span>
                                    <span class="sf-row" style="gap:6px">
                                        <span class="sf-nowrap">{{ $order->shipping_tracking_id }}</span>
                                        <button type="button" class="sf-iconbtn" style="padding:2px 6px"
                                                data-sf-copy="{{ $order->shipping_tracking_id }}" aria-label="Salin nomor resi">
                                            <x-storefront.icon name="copy" :size="14" />
                                        </button>
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="sf-card__body" style="padding-top:0">
                        <h3 class="sf-footer__title">Riwayat status</h3>
                        <x-storefront.order-timeline :order="$order" />
                    </div>

                    @if ($order->items->isNotEmpty())
                        <div class="sf-card__foot">
                            <h3 class="sf-footer__title">Item pesanan</h3>
                            <div class="sf-tablewrap" style="margin-top:10px">
                                <table class="sf-table">
                                    <caption class="sf-sr-only">Item pesanan {{ $order->order_number }}</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Produk</th>
                                            <th scope="col" class="sf-table__num">Jumlah</th>
                                            <th scope="col" class="sf-table__num">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($order->items as $item)
                                            <tr>
                                                <th scope="row" style="font-weight:500">
                                                    {{ $item->product?->name ?? 'Produk tidak tersedia' }}
                                                </th>
                                                <td class="sf-table__num">{{ \App\Support\Currency::number($item->quantity) }}</td>
                                                <td class="sf-table__num">{{ \App\Support\Currency::format($item->sub_total) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <div class="sf-card__foot">
                        <a href="{{ route('orders.show', $order) }}" class="sf-btn sf-btn--outline sf-btn--sm">
                            Lihat detail lengkap
                        </a>
                    </div>
                </article>
            @elseif ($lookedUp)
                <x-storefront.alert type="error" title="Pesanan tidak ditemukan">
                    Nomor pesanan tersebut tidak ditemukan pada akun Anda. Periksa kembali penulisan nomor pesanan
                    atau masuk ke akun yang dipakai saat memesan.
                </x-storefront.alert>

                @guest
                    <p class="sf-small sf-muted" style="margin-top:12px">
                        <a href="{{ route('login') }}">Masuk</a> agar kami dapat menampilkan pesanan yang terhubung dengan akun Anda.
                    </p>
                @endguest
            @endif
        </div>
    </section>
@endsection
