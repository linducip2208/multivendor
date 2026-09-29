@extends('layouts.storefront')

@section('content')
    @php
        $statusBadge = static function (?string $value): string {
            return match (\App\Enums\OrderStatus::fromStored($value)->badge()) {
                'warning' => 'sf-badge--warning',
                'info' => 'sf-badge--info',
                'primary' => 'sf-badge--brand',
                'success' => 'sf-badge--success',
                default => 'sf-badge--neutral',
            };
        };
        $paymentBadge = static fn (string $value): string => match ($value) {
            'paid' => 'sf-badge--success',
            'refunded' => 'sf-badge--info',
            'failed', 'expired' => 'sf-badge--danger',
            default => 'sf-badge--warning',
        };
    @endphp

    <div class="sf-container">
        <nav aria-label="Breadcrumb" class="sf-breadcrumb">
            <a href="{{ route('home') }}">Beranda</a>
            <span class="sf-breadcrumb__sep" aria-hidden="true">/</span>
            <span aria-current="page">Pesanan Saya</span>
        </nav>
    </div>

    <section class="sf-section sf-section--tight" aria-labelledby="sf-orders-title">
        <div class="sf-container">
            <div class="sf-section-head">
                <h1 class="sf-section-head__title" id="sf-orders-title">Pesanan Saya</h1>
                <a href="{{ route('account.dashboard') }}" class="sf-section-head__link">
                      <x-storefront.icon name="home" :size="16" /> Dashboard
                </a>
            </div>

            @if ($orders->total() > 0)
                <div class="sf-stack" style="gap:14px">
                    @foreach ($orders as $order)
                        <article class="sf-card">
                            <div class="sf-card__body">
                                <div class="sf-row sf-row--between sf-row--wrap" style="gap:12px">
                                    <div style="min-width:0">
                                        <a href="{{ route('orders.show', $order) }}" class="sf-bold" style="font-size:1rem">
                                            {{ $order->order_number }}
                                        </a>
                                        <p class="sf-small sf-muted sf-mb-0">
                                            {{ $order->shop?->name ?? 'Toko telah dihapus' }}
                                            &middot;
                                            <time datetime="{{ $order->created_at?->toAtomString() }}">{{ $order->created_at?->translatedFormat('d M Y H:i') }}</time>
                                        </p>
                                    </div>

                                    <div class="sf-row sf-row--wrap" style="gap:8px">
                                        <span class="sf-badge {{ $statusBadge($order->order_status) }}">
                                            {{ \App\Enums\OrderStatus::fromStored($order->order_status)->label() }}
                                        </span>
                                        <span class="sf-badge {{ $paymentBadge((string) $order->payment_status) }}">
                                            {{ \Illuminate\Support\Str::headline((string) $order->payment_status) }}
                                        </span>
                                    </div>
                                </div>

                                <div class="sf-row sf-row--wrap" style="gap:10px;margin-top:12px">
                                    @foreach ($order->items->take(3) as $item)
                                        <span style="width:56px;height:56px;border-radius:var(--sf-radius-sm);overflow:hidden;background:var(--sf-bg-muted);flex-shrink:0">
                                            @if ($item->product?->thumbnail_url)
                                                 <img src="{{ $item->product?->thumbnail_url }}" alt="{{ $item->product?->name ?? 'Produk' }}"
                                                     loading="lazy" width="112" height="112" decoding="async"
                                                     style="width:100%;height:100%;object-fit:cover">
                                            @else
                                                <span class="sf-row" style="justify-content:center;height:100%;color:var(--sf-text-subtle)">
                                                    <x-storefront.icon name="image" :size="20" />
                                                </span>
                                            @endif
                                        </span>
                                    @endforeach
                                    <span class="sf-small sf-muted" style="flex:1 1 auto;min-width:0">
                                        {{ \App\Support\Currency::number($order->items->sum('quantity')) }} item
                                        @if ($order->items->count() > 3)
                                            dari {{ $order->items->count() }} produk
                                        @endif
                                    </span>
                                    <span class="sf-bold sf-nowrap">{{ \App\Support\Currency::format($order->total) }}</span>
                                </div>
                            </div>

                            <div class="sf-card__foot sf-row sf-row--between sf-row--wrap" style="gap:8px">
                                <button type="button" class="sf-btn sf-btn--ghost sf-btn--sm" data-sf-copy="{{ $order->order_number }}" aria-label="Salin nomor pesanan">
                                    <x-storefront.icon name="copy" :size="15" /> Salin nomor
                                </button>
                                <div class="sf-row sf-row--wrap" style="gap:8px">
                                    @if (\App\Enums\OrderStatus::fromStored($order->order_status)->isTerminal() === false)
                                        <a href="{{ route('track-order', ['order_number' => $order->order_number]) }}" class="sf-btn sf-btn--ghost sf-btn--sm">
                                            Lacak
                                        </a>
                                    @endif
                                    @if ($order->delivery_man_id && $order->order_status === 'delivered')
                                        <a href="{{ route('delivery.rate', $order) }}" class="sf-btn sf-btn--outline sf-btn--sm">
                                            <x-storefront.icon name="star" :size="15" :stroke="0" /> Nilai kurir
                                        </a>
                                    @endif
                                    <a href="{{ route('orders.show', $order) }}" class="sf-btn sf-btn--primary sf-btn--sm">
                                        Detail pesanan
                                    </a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <x-storefront.pagination :paginator="$orders" />
            @else
                <x-storefront.empty
                    title="Belum ada pesanan"
                    text="Pesanan yang sudah dibuat akan tampil di sini beserta status pengirimannya."
                    :href="route('products.index')"
                    label="Mulai belanja"
                    icon="package"
                />
            @endif
        </div>
    </section>
@endsection
