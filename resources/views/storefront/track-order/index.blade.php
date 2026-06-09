@extends('layouts.storefront')
@section('title', 'Lacak Pesanan')
@section('content')
<div class="container" style="max-width:500px;">
<h4 class="fw-bold mb-4 text-center"><i class="fas fa-search-location me-2 text-primary"></i> Lacak Pesanan</h4>
<form method="GET" class="mb-4"><div class="input-group input-group-lg"><input type="text" name="order_number" class="form-control" placeholder="Masukkan nomor pesanan..." value="{{ request('order_number') }}" required><button class="btn btn-primary"><i class="fas fa-search"></i></button></div></form>
@if($order)
<div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
    <h6 class="fw-bold">{{ $order->order_number }}</h6>
    <p class="small text-muted">{{ $order->shop->name ?? '' }} · {{ $order->created_at->format('d M Y H:i') }}</p>
    <div class="d-flex justify-content-between mb-3"><span>Total</span><span class="fw-bold">Rp {{ number_format($order->total,0,',','.') }}</span></div>
    <div class="mb-3"><span class="badge bg-{{ ['pending'=>'warning','confirmed'=>'info','processing'=>'primary','shipped'=>'indigo','delivered'=>'success','canceled'=>'danger'][$order->order_status] }}-subtle fs-6">{{ ucfirst($order->order_status) }}</span></div>
    @foreach($order->statusHistory as $h)<div class="d-flex gap-2 mb-2 pb-2 border-bottom small"><span class="text-muted">{{ $h->created_at->format('d/m/Y H:i') }}</span><span>{{ $h->status }}</span>@if($h->note)<span class="text-muted">— {{ $h->note }}</span>@endif</div>@endforeach
    @if($order->items->count()>0)<hr><div class="small">@foreach($order->items as $i)<div class="d-flex justify-content-between">{{ $i->product->name ?? '-' }} ×{{ $i->quantity }}<span>Rp {{ number_format($i->sub_total,0,',','.') }}</span></div>@endforeach</div>@endif
</div></div>
@elseif(request()->has('order_number'))
<div class="alert alert-warning text-center">Pesanan tidak ditemukan.</div>
@endif
</div>
@endsection
