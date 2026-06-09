@extends('layouts.vendor')
@section('title', 'Edit Kupon')
@section('content')
<div class="mb-4"><a href="{{ route('vendor.coupon.index') }}" class="small"><i class="fas fa-arrow-left me-1"></i>Kembali</a><h4 class="fw-bold mt-2">Edit Kupon: {{ $coupon->code }}</h4></div>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
<form method="POST" action="{{ route('vendor.coupon.update', $coupon) }}">@csrf @method('PUT')
<div class="row g-3">
    <div class="col-md-4"><label class="fw-medium">Kode</label><input type="text" name="code" class="form-control" value="{{ old('code', $coupon->code) }}" required></div>
    <div class="col-md-4"><label class="fw-medium">Judul</label><input type="text" name="title" class="form-control" value="{{ old('title', $coupon->title) }}"></div>
    <div class="col-md-4"><label class="fw-medium">Tipe</label><select name="coupon_type" class="form-select"><option value="percentage" {{ $coupon->coupon_type==='percentage'?'selected' : '' }}>%</option><option value="fixed" {{ $coupon->coupon_type==='fixed'?'selected' : '' }}>Rp</option><option value="free_shipping" {{ $coupon->coupon_type==='free_shipping'?'selected' : '' }}>Free Ship</option></select></div>
    <div class="col-md-2"><label class="fw-medium">Nilai</label><input type="number" name="discount_value" class="form-control" value="{{ old('discount_value', $coupon->discount_value) }}" required></div>
    <div class="col-md-2"><label class="fw-medium">Min Belanja</label><input type="number" name="min_purchase" class="form-control" value="{{ old('min_purchase', $coupon->min_purchase) }}"></div>
    <div class="col-md-2"><label class="fw-medium">Max Diskon</label><input type="number" name="max_discount" class="form-control" value="{{ old('max_discount', $coupon->max_discount) }}"></div>
    <div class="col-md-2"><label class="fw-medium">Limit</label><input type="number" name="usage_limit" class="form-control" value="{{ old('usage_limit', $coupon->usage_limit) }}"></div>
    <div class="col-md-3"><label class="fw-medium">Mulai</label><input type="datetime-local" name="start_date" class="form-control" value="{{ old('start_date', $coupon->start_date?->format('Y-m-d\TH:i')) }}"></div>
    <div class="col-md-3"><label class="fw-medium">Berakhir</label><input type="datetime-local" name="end_date" class="form-control" value="{{ old('end_date', $coupon->end_date?->format('Y-m-d\TH:i')) }}"></div>
    <div class="col-md-2"><div class="form-check mt-4"><input type="checkbox" name="status" class="form-check-input" id="st" value="1" {{ $coupon->status?'checked' : '' }}><label for="st" class="fw-medium">Aktif</label></div></div>
    <div class="col-12"><button class="btn btn-success px-4"><i class="fas fa-save me-2"></i>Perbarui</button></div>
</div></form></div></div>
@endsection
