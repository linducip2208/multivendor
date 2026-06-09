@extends('layouts.vendor')
@section('title', 'Invoice')
@section('content')
<div class="mb-4"><a href="{{ route('vendor.orders.show', $order) }}" class="small"><i class="fas fa-arrow-left me-1"></i>Kembali</a></div>
<div class="card border-0 rounded-4 shadow-sm" style="max-width:700px;margin:0 auto"><div class="card-body p-5" id="printArea">
    <div class="d-flex justify-content-between mb-4"><div><h4 class="fw-bold mb-0">INVOICE</h4><small>#{{ $order->order_number }}</small></div><div class="text-end"><h5 class="fw-bold mb-0">{{ $order->shop->name ?? '' }}</h5><small>{{ $order->shop->address ?? '' }}</small></div></div>
    <hr>
    <div class="row mb-4"><div class="col-6"><small class="text-muted">Pelanggan:</small><br><strong>{{ $order->customer->name ?? 'Walk-in' }}</strong></div><div class="col-6 text-end"><small class="text-muted">Tanggal:</small><br>{{ $order->created_at->format('d F Y H:i') }}</div></div>
    <table class="table table-bordered mb-3"><thead><tr><th>Produk</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead><tbody>@foreach($order->items as $item)<tr><td>{{ $item->product->name ?? '-' }}</td><td>{{ $item->quantity }}</td><td>{{ number_format($item->price,0,',','.') }}</td><td>Rp {{ number_format($item->sub_total,0,',','.') }}</td></tr>@endforeach</tbody><tfoot><tr><td colspan="3" class="text-end fw-bold">Total</td><td class="fw-bold fs-5">Rp {{ number_format($order->total,0,',','.') }}</td></tr></tfoot></table>
    <div class="text-center mt-4 small text-muted">Terima kasih telah berbelanja!</div>
</div>
<div class="text-center mt-3"><a href="{{ route('vendor.invoice.download', $order) }}" class="btn btn-primary"><i class="fas fa-download me-2"></i>Download PDF</a> <button onclick="window.print()" class="btn btn-outline-secondary"><i class="fas fa-print me-2"></i>Cetak</button></div>
</div>
@endsection
