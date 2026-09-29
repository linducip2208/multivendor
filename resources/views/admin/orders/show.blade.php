@extends('layouts.admin')
@section('title', 'Detail Pesanan')
@section('content')
<div class="mb-4"><a href="{{ route('admin.orders.index') }}" class="small"><x-admin.icon name="arrow-left" :size="16" class="me-1" />Kembali</a><h4 class="fw-bold mt-2">Order #{{ $order->order_number }}</h4></div>
<div class="row g-4">
    <div class="col-lg-8">
        <x-admin.card :padding="false" class="mb-3"><div class="card-header bg-transparent border-0 pt-3"><h6 class="fw-bold mb-0"><x-admin.icon name="box" :size="16" class="me-2" />Item Pesanan</h6></div>
            <div class="table-responsive"><table class="table mb-0"><thead class="table-light"><tr><th>Produk</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead>
            <tbody>@foreach($order->items as $i)<tr><td class="fw-medium">{{ $i->product->name ?? '-' }}</td><td>{{ $i->quantity }}</td><td>Rp {{ number_format($i->price,0,',','.') }}</td><td>Rp {{ number_format($i->sub_total,0,',','.') }}</td></tr>@endforeach</tbody>
            <tfoot class="table-light"><tr><td colspan="3" class="text-end">Subtotal</td><td>Rp {{ number_format($order->sub_total,0,',','.') }}</td></tr>
            <tr><td colspan="3" class="text-end">Ongkir</td><td>Rp {{ number_format($order->shipping_cost,0,',','.') }}</td></tr>
            @if($order->coupon_discount>0)<tr><td colspan="3" class="text-end">Kupon</td><td>-Rp {{ number_format($order->coupon_discount,0,',','.') }}</td></tr>@endif
            <tr><td colspan="3" class="text-end fw-bold">Total</td><td class="fw-bold fs-5">Rp {{ number_format($order->total,0,',','.') }}</td></tr></tfoot></table></div></x-admin.card>
        <x-admin.card :padding="false"><div class="card-header bg-transparent border-0 pt-3"><h6 class="fw-bold mb-0"><x-admin.icon name="history" :size="16" class="me-2" />Riwayat Status</h6></div><div class="card-body">@foreach($order->statusHistory as $h)<div class="d-flex gap-3 mb-2 pb-2 border-bottom small"><x-admin.badge color="info" :text="ucfirst($h->status)" /><span class="text-muted">{{ $h->created_at->format('d/m/Y H:i') }}</span>@if($h->note)<span>{{ $h->note }}</span>@endif</div>@endforeach</div></x-admin.card>
    </div>
    <div class="col-lg-4">
        <x-admin.card :padding="false" class="mb-3"><div class="card-body">
            <h6 class="fw-bold mb-3"><x-admin.icon name="info" :size="16" class="me-2" />Info</h6>
            <div class="mb-2"><small class="text-muted">Pelanggan</small><br>{{ $order->customer->name ?? '-' }}<br><small>{{ $order->customer->email ?? '' }}</small></div>
            <div class="mb-2"><small class="text-muted">Toko</small><br>{{ $order->shop->name ?? '-' }}</div>
            <div class="mb-2"><small class="text-muted">Status</small><br><x-admin.badge color="info" :text="ucfirst($order->order_status)" /></div>
            <div class="mb-2"><small class="text-muted">Pembayaran</small><br>{{ $order->payment_method }} — <x-admin.badge :color="$order->payment_status==='paid'?'success' : 'warning'" :text="$order->payment_status" /></div>
            @if($order->shipping_tracking_id)<div class="mb-2"><small class="text-muted">Resi</small><br><code>{{ $order->shipping_tracking_id }}</code></div>@endif
            @if($order->shipping_address)<div class="mb-2"><small class="text-muted">Alamat</small><br><small>{{ $order->shipping_address['receiver_name']??'' }} — {{ $order->shipping_address['address']??'' }}</small></div>@endif
        </div></x-admin.card>

        @if(!in_array($order->order_status, ['delivered','canceled']))
        <x-admin.card :padding="false"><div class="card-header bg-transparent border-0 pt-3"><h6 class="fw-bold mb-0"><x-admin.icon name="settings" :size="16" class="me-2" />Update Status</h6></div><div class="card-body">
            <form method="POST" action="{{ route('admin.orders.update-status', $order) }}">@csrf @method('PUT')
                <div class="mb-2"><select name="status" class="form-select" required>
                    @if($order->order_status==='pending')<option value="confirmed">Konfirmasi</option>@endif
                    @if(in_array($order->order_status,['pending','confirmed']))<option value="processing">Proses</option>@endif
                    @if(in_array($order->order_status,['confirmed','processing']))<option value="shipped">Kirim</option>@endif
                    @if($order->order_status==='shipped')<option value="delivered">Sampai</option>@endif
                    <option value="canceled">Batalkan</option>
                </select></div>
                <div class="mb-2"><input type="text" name="tracking_id" class="form-control form-control-sm" placeholder="Nomor resi (opsional)"></div>
                <div class="mb-2"><textarea name="note" class="form-control form-control-sm" rows="2" placeholder="Catatan"></textarea></div>
                <button class="btn btn-primary w-100"><x-admin.icon name="check" :size="16" class="me-2" />Update</button>
            </form>
        </div></x-admin.card>
        @endif
    </div>
</div>
@endsection
