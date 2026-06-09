@extends('layouts.admin')
@section('title', 'Tambah Kupon')
@section('content')
<div class="mb-4"><a href="{{ route('admin.coupons.index') }}" class="small"><i class="fas fa-arrow-left me-1"></i>Kembali</a><h4 class="fw-bold mt-2">Tambah Kupon</h4></div>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
<form method="POST" action="{{ route('admin.coupons.store') }}">@csrf
<div class="row g-3">
    <div class="col-md-4"><label class="fw-medium">Kode Kupon <span class="text-danger">*</span></label><input type="text" name="code" class="form-control" value="{{ old('code') }}" placeholder="HEMAT50" required></div>
    <div class="col-md-4"><label class="fw-medium">Judul</label><input type="text" name="title" class="form-control" value="{{ old('title') }}" placeholder="Diskon 50%"></div>
    <div class="col-md-4"><label class="fw-medium">Tipe <span class="text-danger">*</span></label><select name="coupon_type" class="form-select" required><option value="percentage">Persentase (%)</option><option value="fixed">Nominal (Rp)</option><option value="free_shipping">Gratis Ongkir</option></select></div>
    <div class="col-md-2"><label class="fw-medium">Nilai Diskon <span class="text-danger">*</span></label><input type="number" name="discount_value" class="form-control" value="{{ old('discount_value', 10) }}" min="0" required></div>
    <div class="col-md-2"><label class="fw-medium">Min Belanja</label><input type="number" name="min_purchase" class="form-control" value="{{ old('min_purchase', 0) }}" min="0"></div>
    <div class="col-md-2"><label class="fw-medium">Max Diskon</label><input type="number" name="max_discount" class="form-control" value="{{ old('max_discount') }}" min="0"></div>
    <div class="col-md-2"><label class="fw-medium">Batas Pakai</label><input type="number" name="usage_limit" class="form-control" value="{{ old('usage_limit') }}" min="1"></div>
    <div class="col-md-3"><label class="fw-medium">Mulai</label><input type="datetime-local" name="start_date" class="form-control" value="{{ old('start_date') }}"></div>
    <div class="col-md-3"><label class="fw-medium">Berakhir</label><input type="datetime-local" name="end_date" class="form-control" value="{{ old('end_date') }}"></div>
    <div class="col-md-2"><div class="form-check mt-4"><input type="checkbox" name="status" class="form-check-input" id="st" value="1" checked><label for="st" class="fw-medium">Aktif</label></div></div>
    <div class="col-12"><button class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button></div>
</div>
</form>
</div></div>
@endsection
