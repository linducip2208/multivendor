@extends('layouts.storefront')

@section('content')
    @php
        $statusEnum = \App\Enums\OrderStatus::fromStored($order->order_status);
        $statusBadge = match ($statusEnum->badge()) {
            'warning' => 'sf-badge--warning',
            'info' => 'sf-badge--info',
            'primary' => 'sf-badge--brand',
            'success' => 'sf-badge--success',
            default => 'sf-badge--neutral',
        };
        $paymentBadge = match ((string) $order->payment_status) {
            'paid' => 'sf-badge--success',
            'refunded' => 'sf-badge--info',
            'failed', 'expired' => 'sf-badge--danger',
            default => 'sf-badge--warning',
        };
        $address = is_array($order->shipping_address) ? $order->shipping_address : [];
        $canRefund = $order->payment_status === 'paid' && $order->order_status === 'delivered';
        $grouped = $order->items->groupBy(fn ($item) => $item->product?->shop_id ?? 0);
        $transaction = collect($order->transaction)->first();
    @endphp

    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <a href="{{ route('orders.index') }}">Pesanan Saya</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">{{ $order->order_number }}</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-order-title">
        <div class="sf-container">
            <div class="sf-section-head">
                <div style="min-width:0">
                    <h1 class="sf-section-head__title" id="sf-order-title" style="font-size:clamp(1.35rem,1.1rem+1vw,1.9rem)">
                        Pesanan {{ $order->order_number }}
                    </h1>
                    <p class="sf-small sf-muted sf-mb-0">
                        Dibuat
                        <time datetime="{{ $order->created_at?->toAtomString() }}">{{ $order->created_at?->translatedFormat('d M Y H:i') }}</time>
                        · Invoice <span class="sf-bold">{{ $invoiceNumber ?? $order->invoiceNumber() }}</span>
                    </p>
                </div>
                <div class="sf-row sf-row--wrap" style="gap:8px">
                    <span class="sf-badge {{ $statusBadge }}">{{ $statusEnum->label() }}</span>
                    <span class="sf-badge {{ $paymentBadge }}">{{ \Illuminate\Support\Str::headline((string) $order->payment_status) }}</span>
                    <button type="button" class="sf-btn sf-btn--outline sf-btn--sm" data-sf-copy="{{ $order->order_number }}" aria-label="Salin nomor pesanan">
                        <x-storefront.icon name="copy" :size="15" /> Salin
                    </button>
                </div>
            </div>

            <div class="sf-cartlayout">
                <div class="sf-stack" style="gap:20px">
                    <section class="sf-card" aria-labelledby="sf-order-progress">
                        <div class="sf-card__body">
                            <h2 class="sf-footer__title" id="sf-order-progress">Riwayat status</h2>
                            <x-storefront.order-timeline :order="$order" />
                        </div>
                    </section>

                    @foreach ($grouped as $shopId => $items)
                        <section class="sf-card" aria-labelledby="sf-order-vendor-{{ $shopId }}">
                            <div class="sf-card__body">
                                <h2 class="sf-footer__title" id="sf-order-vendor-{{ $shopId }}">
                                    @if ($items->first()?->product?->shop)
                                        <a href="{{ $items->first()?->product?->shop?->slug ? route('shop.show', $items->first()->product->shop->slug) : route('products.index') }}">{{ $items->first()?->product?->shop?->name ?? 'Produk' }}</a>
                                    @else
                                        Produk
                                    @endif
                                </h2>

                                <div class="sf-tablewrap" style="margin-top:12px">
                                    <table class="sf-table">
                                        <caption class="sf-sr-only">Item pesanan dari {{ $items->first()?->product?->shop?->name ?? 'penjual' }}</caption>
                                        <thead>
                                            <tr>
                                                <th scope="col">Produk</th>
                                                <th scope="col" class="sf-table__num">Harga</th>
                                                <th scope="col" class="sf-table__num">Jumlah</th>
                                                <th scope="col" class="sf-table__num">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($items as $item)
                                                <tr>
                                                    <th scope="row" style="font-weight:500;min-width:220px">
                                                        <a href="{{ $item->product?->storefront_url ?? route('products.index') }}" class="sf-row" style="gap:10px">
                                                            <span style="width:44px;height:44px;border-radius:var(--sf-radius-xs);overflow:hidden;background:var(--sf-bg-muted);flex-shrink:0">
                                                                @if ($item->product?->thumbnail_url)
                                                                     <img src="{{ $item->product?->thumbnail_url }}" alt="{{ $item->product?->name ?? 'Produk' }}"
                                                                         loading="lazy" width="88" height="88" decoding="async"
                                                                         style="width:100%;height:100%;object-fit:cover">
                                                                @else
                                                                    <span class="sf-row" style="justify-content:center;height:100%;color:var(--sf-text-subtle)">
                                                                        <x-storefront.icon name="image" :size="18" />
                                                                    </span>
                                                                @endif
                                                            </span>
                                                            <span style="min-width:0">
                                                                <span class="sf-clamp-2" style="display:-webkit-box;color:var(--sf-text)">{{ $item->product?->name ?? 'Produk tidak tersedia' }}</span>
                                                                @if ($item->variant?->variant || $item->variant_detail)
                                                                    <span class="sf-tiny sf-muted" style="display:block">
                                                                        Varian: {{ $item->variant?->variant ?: $item->variant_detail }}
                                                                    </span>
                                                                @endif
                                                            </span>
                                                        </a>
                                                    </th>
                                                    <td class="sf-table__num">{{ \App\Support\Currency::format($item->price) }}</td>
                                                    <td class="sf-table__num">{{ \App\Support\Currency::number($item->quantity) }}</td>
                                                    <td class="sf-table__num sf-bold">{{ \App\Support\Currency::format($item->sub_total) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            @if ($order->items->contains(fn ($item) => $item->product?->product_type === 'digital'))
                                <div class="sf-card__foot sf-stack" style="gap:10px">
                                    <p class="sf-small sf-bold sf-mb-0">Produk digital</p>
                                    @foreach ($items as $item)
                                        @if ($item->product?->product_type === 'digital')
                                            <div class="sf-row sf-row--wrap" style="gap:8px">
                                                <span class="sf-small sf-clamp-2" style="flex:1 1 180px;min-width:0">
                                                    {{ $item->product?->name ?? 'Produk tidak tersedia' }}
                                                </span>
                                                <form method="POST" action="{{ route('download.otp', $item) }}">
                                                    @csrf
                                                    <button type="submit" class="sf-btn sf-btn--outline sf-btn--sm">
                                                        <x-storefront.icon name="mail" :size="14" /> Kirim kode OTP
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('download.verify', $item) }}" class="sf-row" style="gap:6px">
                                                    @csrf
                                                    <div class="sf-field">
                                                        <label class="sf-sr-only" for="sf-otp-{{ $item->id }}">Kode OTP unduhan</label>
                                                        <input class="sf-input" id="sf-otp-{{ $item->id }}" type="text" name="otp" required
                                                               size="6" maxlength="6" inputmode="numeric" placeholder="OTP"
                                                               style="width:110px">
                                                    </div>
                                                    <button type="submit" class="sf-btn sf-btn--primary sf-btn--sm">Unduh</button>
                                                </form>
                                            </div>
                                        @endif
                                    @endforeach
                                    <p class="sf-tiny sf-muted sf-mb-0">
                                        Kode OTP dikirim lewat kanal notifikasi akun Anda dan tidak pernah ditampilkan di halaman ini.
                                    </p>
                                </div>
                            @endif

                            @if ($canRefund || $order->items->contains(fn ($item) => ($item->refund_status ?? 'none') !== 'none'))
                                <div class="sf-card__foot sf-stack" style="gap:10px">
                                    @foreach ($items as $item)
                                        @if (($item->refund_status ?? 'none') !== 'none')
                                            <p class="sf-small sf-mb-0">
                                                <span class="sf-badge sf-badge--warning">Refund: {{ \Illuminate\Support\Str::headline((string) $item->refund_status) }}</span>
                                                @if ($item->refund_admin_note)
                                                    <span class="sf-muted">{{ $item->refund_admin_note }}</span>
                                                @endif
                                            </p>
                                        @elseif ($canRefund)
                                            <details>
                                                <summary class="sf-small" style="cursor:pointer;color:var(--sf-danger)">Ajukan refund untuk {{ $item->product?->name ?? 'produk ini' }}</summary>
                                                <form method="POST" action="{{ route('orders.refund.request', $item) }}" class="sf-stack" style="gap:8px;margin-top:10px">
                                                    @csrf
                                                    <div class="sf-field">
                                                        <label class="sf-label" for="sf-refund-{{ $item->id }}">Alasan refund (per item)</label>
                                                        <select class="sf-select" id="sf-refund-{{ $item->id }}" name="reason" required>
                                                            <option value="">Pilih alasan</option>
                                                            @foreach (($returnReasons ?? \App\Models\OrderReturn::reasonLabels()) as $key => $label)
                                                                <option value="{{ $key }}">{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('reason')
                                                            <span class="sf-error">{{ $message }}</span>
                                                        @enderror
                                                    </div>
                                                    <div class="sf-field">
                                                        <label class="sf-label" for="sf-refund-detail-{{ $item->id }}">Rincian (opsional, min. 10 karakter bila diisi)</label>
                                                        <textarea class="sf-textarea" id="sf-refund-detail-{{ $item->id }}" name="reason_detail"
                                                              minlength="10" maxlength="2000" rows="2"
                                                              placeholder="Contoh: layar retak saat paket dibuka"></textarea>
                                                    </div>
                                                    <div>
                                                        <button type="submit" class="sf-btn sf-btn--outline sf-btn--sm">Kirim permintaan refund</button>
                                                    </div>
                                                </form>
                                            </details>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </section>
                    @endforeach
                </div>

                <aside class="sf-stack" style="gap:16px">
                    <section class="sf-panel" aria-labelledby="sf-order-summary">
                        <h2 class="sf-footer__title" id="sf-order-summary">Ringkasan pembayaran</h2>
                        <div class="sf-summary">
                            <div class="sf-summary__row">
                                <span class="sf-summary__label">Subtotal</span>
                                <span>{{ \App\Support\Currency::format($order->sub_total) }}</span>
                            </div>
                            <div class="sf-summary__row">
                                <span class="sf-summary__label">Ongkos kirim</span>
                                <span>{{ \App\Support\Currency::format($order->shipping_cost) }}</span>
                            </div>
                            @if ((float) $order->tax > 0)
                                <div class="sf-summary__row">
                                    <span class="sf-summary__label">Pajak</span>
                                    <span>{{ \App\Support\Currency::format($order->tax) }}</span>
                                </div>
                            @endif
                            @if ((float) $order->coupon_discount > 0)
                                <div class="sf-summary__row">
                                    <span class="sf-summary__label">Kupon {{ $order->coupon_code }}</span>
                                    <span class="sf-summary__value--discount">-{{ \App\Support\Currency::format($order->coupon_discount) }}</span>
                                </div>
                            @endif
                            <div class="sf-summary__row sf-summary__row--total">
                                <span>Total</span>
                                <span>{{ \App\Support\Currency::format($order->total) }}</span>
                            </div>
                        </div>
                    </section>

                    <section class="sf-panel" aria-labelledby="sf-order-meta">
                        <h2 class="sf-footer__title" id="sf-order-meta">Informasi pengiriman</h2>
                        <div class="sf-summary">
                            @if ($order->shop?->slug)
                                <div class="sf-summary__row">
                                    <span class="sf-summary__label">Toko</span>
                                    <a href="{{ route('shop.show', $order->shop->slug) }}">{{ $order->shop->name ?? 'Toko' }}</a>
                                </div>
                            @endif
                            <div class="sf-summary__row">
                                <span class="sf-summary__label">Kurir</span>
                                <span>{{ $order->shipping_method ?: '-' }}</span>
                            </div>
                            <div class="sf-summary__row">
                                <span class="sf-summary__label">Layanan</span>
                                <span>{{ $order->shipping_service ?: '-' }}</span>
                            </div>
                            @if ($order->shipping_tracking_id)
                                <div class="sf-summary__row">
                                    <span class="sf-summary__label">Resi</span>
                                    <span class="sf-row" style="gap:6px">
                                        <span class="sf-nowrap">{{ $order->shipping_tracking_id }}</span>
                                        <button type="button" class="sf-iconbtn" style="padding:2px 6px" data-sf-copy="{{ $order->shipping_tracking_id }}" aria-label="Salin nomor resi">
                                            <x-storefront.icon name="copy" :size="14" />
                                        </button>
                                    </span>
                                </div>
                            @endif
                            <div class="sf-summary__row">
                                <span class="sf-summary__label">Metode bayar</span>
                                <span>{{ \Illuminate\Support\Str::headline((string) ($order->payment_method ?: '-')) }}</span>
                            </div>
                        </div>

                        @if ($address !== [])
                            <hr class="sf-divider">
                            <p class="sf-small sf-bold sf-mb-0">{{ $address['receiver_name'] ?? '' }}</p>
                            <p class="sf-small sf-muted sf-mb-0">{{ $address['receiver_phone'] ?? '' }}</p>
                            <p class="sf-small sf-muted sf-mb-0" style="overflow-wrap:anywhere">
                                {{ $address['address'] ?? '' }},
                                {{ collect([$address['city'] ?? null, $address['province'] ?? null, $address['postal_code'] ?? null])->filter()->implode(', ') }}
                            </p>
                        @endif

                        @if ($order->note)
                            <hr class="sf-divider">
                            <p class="sf-tiny sf-bold sf-muted sf-mb-0">Catatan Anda</p>
                            <p class="sf-small sf-mb-0" style="overflow-wrap:anywhere">{{ $order->note }}</p>
                        @endif
                    </section>

                    @if ($transaction)
                        <section class="sf-panel" aria-labelledby="sf-order-transaction">
                            <h2 class="sf-footer__title" id="sf-order-transaction">Transaksi</h2>
                            <div class="sf-summary">
                                <div class="sf-summary__row">
                                    <span class="sf-summary__label">ID transaksi</span>
                                    <span class="sf-nowrap">{{ $transaction->transaction_id }}</span>
                                </div>
                                <div class="sf-summary__row">
                                    <span class="sf-summary__label">Status</span>
                                    <span>{{ \Illuminate\Support\Str::headline((string) $transaction->status) }}</span>
                                </div>
                            </div>
                        </section>
                    @endif

                    @if ($order->delivery_man_id)
                        <a href="{{ route('delivery.rate', $order) }}" class="sf-btn sf-btn--outline sf-btn--block">
                            <x-storefront.icon name="star" :size="16" :stroke="0" /> Nilai pengalaman pengiriman
                        </a>
                    @endif

                    <a href="{{ route('orders.index') }}" class="sf-btn sf-btn--ghost sf-btn--block">Kembali ke daftar pesanan</a>
                </aside>
            </div>
        </div>
    </section>
@endsection
