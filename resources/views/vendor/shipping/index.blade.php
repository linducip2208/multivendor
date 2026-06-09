@extends('layouts.vendor')
@section('title','Shipping Methods')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-shipping-fast me-2 text-primary"></i> Metode Pengiriman Toko</h4>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
<form action="{{ route('vendor.shipping.update') }}" method="POST">@csrf @method('PUT')
<div class="row g-3">
    <div class="col-12"><h6 class="fw-bold">Kurir Tersedia</h6>
@php $couriers=['jne'=>'JNE','jnt'=>'J&T Express','sicepat'=>'SiCepat','tiki'=>'TIKI','pos'=>'POS Indonesia','anteraja'=>'AnterAja','lion'=>'Lion Parcel','idexpress'=>'iDexpress','gosend'=>'GoSend','grab'=>'GrabExpress']; @endphp
@foreach($couriers as $code=>$name)
<div class="border rounded-3 p-2 mb-2 d-flex align-items-center gap-3">
    <div class="form-check form-switch mb-0"><input type="checkbox" name="couriers[{{ $code }}]" class="form-check-input" value="1" {{ \App\Models\SystemSetting::get('shop_shipping_'.auth('vendor')->user()->shop->id.'_'.$code) ? 'checked' : '' }}></div>
    <span class="fw-medium">{{ $name }}</span>
    <input type="number" name="costs[{{ $code }}]" class="form-control form-control-sm" style="width:120px;" placeholder="Ongkir (Rp)" value="{{ \App\Models\SystemSetting::get('shop_shipping_'.auth('vendor')->user()->shop->id.'_'.$code.'_cost','15000') }}">
</div>
@endforeach
</div>
<div class="col-12"><button class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button></div>
</div></form></div></div>
@endsection
