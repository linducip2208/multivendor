@extends('layouts.vendor')
@section('title', 'Pengaturan Toko')
@section('content')
<h4 class="fw-bold mb-1"><x-admin.icon name="settings" :size="16" class="me-2 text-secondary" /> Pengaturan Toko</h4>
<p class="text-muted small mb-4">{{ $shop->name }}</p>
<x-admin.card :padding="false"><div class="card-body p-4">
<form action="{{ route('vendor.shop.update') }}" method="POST">@csrf @method('PUT')
<div class="row g-3">
    <div class="col-md-6"><label class="fw-medium">Nama Toko <span class="text-danger">*</span></label><input type="text" name="shop_name" class="form-control" value="{{ old('shop_name', $shop->name) }}" required></div>
    <div class="col-md-6"><label class="fw-medium">Email Toko</label><input type="email" name="email" class="form-control" value="{{ old('email', $shop->email) }}"></div>
    <div class="col-md-6"><label class="fw-medium">Nomor HP</label><input type="text" name="phone" class="form-control" value="{{ old('phone', $shop->phone) }}"></div>
    <div class="col-md-6"><label class="fw-medium">Alamat</label><input type="text" name="address" class="form-control" value="{{ old('address', $shop->address) }}"></div>
    <div class="col-12"><label class="fw-medium">Deskripsi Toko</label><textarea name="description" class="form-control" rows="4">{{ old('description', $shop->description) }}</textarea></div>
    <div class="col-md-6"><label class="fw-medium">URL Logo</label><input type="text" name="logo" class="form-control" value="{{ old('logo', $shop->logo) }}" placeholder="https://..."></div>
    <div class="col-md-6"><label class="fw-medium">URL Banner</label><input type="text" name="banner" class="form-control" value="{{ old('banner', $shop->banner) }}" placeholder="https://..."></div>
    <div class="col-12 mt-3"><h6 class="fw-bold"><x-admin.icon name="building" :size="16" class="me-2" /> Info Bank (Pencairan)</h6></div>
    <div class="col-md-4"><label class="fw-medium">Nama Bank</label><input type="text" name="bank_name" class="form-control" value="{{ old('bank_name', $shop->bank_name) }}" placeholder="BCA"></div>
    <div class="col-md-4"><label class="fw-medium">Nomor Rekening</label><input type="text" name="bank_account_number" class="form-control" value="{{ old('bank_account_number', $shop->bank_account_number) }}"></div>
    <div class="col-md-4"><label class="fw-medium">Atas Nama</label><input type="text" name="bank_account_name" class="form-control" value="{{ old('bank_account_name', $shop->bank_account_name) }}"></div>
    <div class="col-12"><button class="btn btn-primary px-4"><x-admin.icon name="check" :size="16" class="me-2" />Simpan Pengaturan</button></div>
</div>
</form></div></x-admin.card>
@endsection
