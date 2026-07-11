@extends('layouts.vendor')
@section('title', 'POS Print')
@section('content')
<div id="printArea" style="max-width:300px;margin:0 auto;font-family:monospace;font-size:12px;">
    <div class="text-center mb-2">
        <strong style="font-size:14px;">{{ $order->shop->name ?? '' }}</strong><br>
        <small>{{ $order->shop->address ?? '' }}</small>
    </div>
    <hr style="border-top:1px dashed #999;margin:4px 0">
    <div>Order: {{ $order->order_number }}</div>
    <div>Tanggal: {{ $order->created_at->format('d/m/Y H:i') }}</div>
    <div>Kasir: {{ auth('vendor')->user()->name }}</div>
    <hr style="border-top:1px dashed #999;margin:4px 0">
    @foreach($order->items as $item)
    <div>{{ $item->product->name ?? '-' }}</div>
    <div style="display:flex;justify-content:space-between">
        <span>{{ $item->quantity }} x Rp {{ number_format($item->price,0,',','.') }}</span>
        <span>Rp {{ number_format($item->sub_total,0,',','.') }}</span>
    </div>
    @endforeach
    <hr style="border-top:1px dashed #999;margin:4px 0">
    <div style="display:flex;justify-content:space-between;font-weight:bold;font-size:14px">
        <span>TOTAL</span>
        <span>Rp {{ number_format($order->total,0,',','.') }}</span>
    </div>
    @if($order->discount > 0)
    <small>Diskon: Rp {{ number_format($order->discount,0,',','.') }}</small>
    @endif
    <hr style="border-top:1px dashed #999;margin:4px 0">
    <div class="text-center mt-2">
        <small>{{ $order->payment_method === 'cash' ? 'PEMBAYARAN CASH' : ($order->payment_method === 'qris' ? 'PEMBAYARAN QRIS' : 'PEMBAYARAN TRANSFER') }}</small>
        <br><small>--- Terima Kasih ---</small>
    </div>
</div>
<div class="text-center mt-3 no-print">
    <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-2"></i>Cetak</button>
    <a href="{{ route('vendor.pos.index') }}" class="btn btn-outline-secondary">Kembali ke POS</a>
</div>
<style>@media print{body *{visibility:hidden}#printArea,#printArea *{visibility:visible}#printArea{position:absolute;left:0;top:0;width:100%}.no-print{display:none!important}}</style>
@endsection
