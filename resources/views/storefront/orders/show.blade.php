@extends('layouts.storefront')
@section('title', 'Detail Pesanan')

@section('content')
<div class="container">
    <a href="{{ route('orders.index') }}" class="text-decoration-none small"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
    <h4 class="fw-bold mt-2 mb-4">Order #{{ $order->order_number }}</h4>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-3">
                <div class="card-header bg-transparent border-0 pt-3"><h6 class="fw-bold mb-0">Item Pesanan</h6></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light"><tr><th>Produk</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead>
                        <tbody>
                            @foreach($order->items as $item)
                            <tr><td>{{ $item->product->name ?? 'Produk' }}</td><td>{{ $item->quantity }}</td><td>Rp {{ number_format($item->price, 0, ',', '.') }}</td><td>Rp {{ number_format($item->sub_total, 0, ',', '.') }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr><td colspan="3" class="text-end">Subtotal</td><td>Rp {{ number_format($order->sub_total, 0, ',', '.') }}</td></tr>
                            <tr><td colspan="3" class="text-end">Ongkir</td><td>Rp {{ number_format($order->shipping_cost, 0, ',', '.') }}</td></tr>
                            @if($order->coupon_discount > 0)<tr><td colspan="3" class="text-end">Kupon</td><td>-Rp {{ number_format($order->coupon_discount, 0, ',', '.') }}</td></tr>@endif
                            <tr><td colspan="3" class="text-end fw-bold">Total</td><td class="fw-bold">Rp {{ number_format($order->total, 0, ',', '.') }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            @if($order->statusHistory->count() > 0)
            <div class="card border-0 shadow-sm rounded-4"><div class="card-header"><h6 class="fw-bold mb-0">Riwayat Status</h6></div>
                <div class="card-body">
                    @foreach($order->statusHistory as $h)
                    <div class="d-flex gap-3 mb-2 pb-2 border-bottom small">
                        <span class="badge bg-info-subtle text-info">{{ ucfirst($h->status) }}</span>
                        <span class="text-muted">{{ $h->created_at->format('d/m/Y H:i') }}</span>
                        @if($h->note)<span>{{ $h->note }}</span>@endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Info Pesanan</h6>
                    <div class="mb-2"><small class="text-muted">Status</small><br><span class="badge bg-info-subtle text-info">{{ ucfirst($order->order_status) }}</span></div>
                    <div class="mb-2"><small class="text-muted">Pembayaran</small><br>{{ $order->payment_method }} — <span class="badge bg-{{ $order->payment_status==='paid'?'success' : 'warning' }}-subtle">{{ $order->payment_status }}</span></div>
                    <div class="mb-2"><small class="text-muted">Toko</small><br>{{ $order->shop->name ?? '-' }}</div>
                    @if($order->shipping_address)
                    <div class="mb-2"><small class="text-muted">Alamat</small><br><small>{{ $order->shipping_address['receiver_name'] ?? '' }} — {{ $order->shipping_address['address'] ?? '' }}</small></div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
