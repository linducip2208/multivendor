@extends('layouts.vendor')

@section('title', 'Detail Pesanan')

@section('content')
<div class="mb-4">
    <a href="{{ route('vendor.orders.index') }}" class="text-decoration-none small"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
    <h4 class="fw-bold mt-2 mb-1">Order #{{ $order->order_number }}</h4>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 rounded-4 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-3 px-3"><h6 class="fw-bold mb-0"><i class="fas fa-box me-2"></i> Item Pesanan</h6></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead class="table-light"><tr><th>Produk</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead>
                    <tbody>
                        @foreach($order->items as $item)
                        <tr>
                            <td>
                                <div class="fw-medium">{{ $item->product->name ?? 'Produk' }}</div>
                                @if($item->variant_detail)<small class="text-muted">{{ $item->variant_detail }}</small>@endif
                            </td>
                            <td>{{ $item->quantity }}</td>
                            <td>Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format($item->sub_total, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr><td colspan="3" class="text-end fw-medium">Subtotal</td><td>Rp {{ number_format($order->sub_total, 0, ',', '.') }}</td></tr>
                        <tr><td colspan="3" class="text-end">Ongkir</td><td>Rp {{ number_format($order->shipping_cost, 0, ',', '.') }}</td></tr>
                        @if($order->coupon_discount > 0)<tr><td colspan="3" class="text-end">Kupon</td><td>-Rp {{ number_format($order->coupon_discount, 0, ',', '.') }}</td></tr>@endif
                        <tr><td colspan="3" class="text-end fw-bold">Total</td><td class="fw-bold">Rp {{ number_format($order->total, 0, ',', '.') }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-3 px-3"><h6 class="fw-bold mb-0"><i class="fas fa-history me-2"></i> Riwayat Status</h6></div>
            <div class="card-body">
                @forelse($order->statusHistory as $h)
                <div class="d-flex gap-3 mb-3 pb-3 border-bottom">
                    <div class="badge bg-info-subtle text-info mt-1">{{ ucfirst($h->status) }}</div>
                    <div>
                        <small>{{ $h->created_at->format('d/m/Y H:i') }}</small>
                        @if($h->note)<div class="small text-muted">{{ $h->note }}</div>@endif
                    </div>
                </div>
                @empty
                <p class="text-muted small">Belum ada riwayat</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 rounded-4 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-3 px-3"><h6 class="fw-bold mb-0"><i class="fas fa-info-circle me-2"></i> Info Pesanan</h6></div>
            <div class="card-body">
                <div class="mb-2"><small class="text-muted">Status</small><div><span class="badge bg-{{ ['pending'=>'warning','confirmed'=>'info','processing'=>'primary','shipped'=>'indigo','delivered'=>'success','canceled'=>'danger'][$order->order_status] }}-subtle">{{ ucfirst($order->order_status) }}</span></div></div>
                <div class="mb-2"><small class="text-muted">Pembayaran</small><div><span class="badge bg-{{ $order->payment_status === 'paid' ? 'success' : 'warning' }}-subtle">{{ $order->payment_status }}</span></div></div>
                <div class="mb-2"><small class="text-muted">Pelanggan</small><div>{{ $order->customer->name ?? '-' }}<br><small>{{ $order->customer->email ?? '' }}</small></div></div>
                @if($order->shipping_address)
                <div class="mb-2"><small class="text-muted">Alamat Kirim</small><div class="small">{{ $order->shipping_address['address'] ?? '-' }}, {{ $order->shipping_address['city'] ?? '' }}</div></div>
                @endif
                @if($order->note)
                <div class="mb-2"><small class="text-muted">Catatan</small><div class="small">{{ $order->note }}</div></div>
                @endif
            </div>
        </div>

        @if(in_array($order->order_status, ['pending', 'confirmed']))
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-3 px-3"><h6 class="fw-bold mb-0"><i class="fas fa-cog me-2"></i> Update Status</h6></div>
            <div class="card-body">
                <form method="POST" action="{{ route('vendor.orders.update-status', $order) }}">
                    @csrf @method('PUT')
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Status Baru</label>
                        <select name="status" class="form-select" required>
                            @if($order->order_status === 'pending')<option value="confirmed">Konfirmasi Pesanan</option>@endif
                            @if(in_array($order->order_status, ['pending', 'confirmed']))<option value="processing">Proses Pesanan</option>@endif
                            <option value="canceled">Batalkan Pesanan</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Catatan</label>
                        <textarea name="note" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-check me-2"></i> Update Status</button>
                </form>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
