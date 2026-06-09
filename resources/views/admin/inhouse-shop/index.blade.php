@extends('layouts.admin')
@section('title','Inhouse Shop')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-store me-2 text-success"></i> Inhouse Shop</h4>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
<form action="{{ route('admin.inhouse-shop.update') }}" method="POST">@csrf @method('PUT')
<div class="row g-3">
    <div class="col-md-6"><div class="form-check form-switch"><input type="checkbox" name="inhouse_active" class="form-check-input" value="1" {{ \App\Models\SystemSetting::get('inhouse_shop_active')?'checked' : '' }}><label class="fw-medium">Aktifkan Inhouse Shop</label><br><small class="text-muted">Admin bisa menjual produk sendiri (tanpa vendor)</small></div></div>
    <div class="col-md-6"><label class="fw-medium">Nama Toko Inhouse</label><input type="text" name="inhouse_name" class="form-control" value="{{ \App\Models\SystemSetting::get('inhouse_shop_name',config('app.name').' Official Store') }}"></div>
    <div class="col-12"><button class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button></div>
</div></form></div></div>
@endsection
