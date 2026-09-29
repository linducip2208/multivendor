@extends('layouts.vendor')
@section('title', 'Pengaturan Toko')
@section('content')
<h4 class="fw-bold mb-1"><x-admin.icon name="settings" :size="16" class="me-2 text-secondary" /> Pengaturan Toko</h4>
<p class="text-muted small mb-4">{{ $shop->name }}</p>
{{-- Kepercayaan toko: progres KYC + skor publik (aditif, dihitung dari data existing) --}}
@php
    $skorSvc = app(\App\Services\Kepercayaan\SkorToko::class);
    try { $kycShop = $skorSvc->kycDariShop($shop); } catch (\Throwable) { $kycShop = null; }
    try { $trustShop = $skorSvc->skorDariShop($shop); } catch (\Throwable) { $trustShop = null; }
@endphp
@if ($kycShop || $trustShop)
<div class="row g-3 mb-3">
    @if ($kycShop)
    <div class="col-12 col-md-7">
        <x-admin.card><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-semibold">Verifikasi bertahap (KYC)</span>
                <span class="badge bg-blue-lt">{{ $kycShop['done'] }}/4 · {{ $kycShop['percent'] }}%</span>
            </div>
            <div class="progress mb-2" role="progressbar" aria-valuenow="{{ $kycShop['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progres verifikasi toko">
                <div class="progress-bar" style="width: {{ $kycShop['percent'] }}%"></div>
            </div>
            <ul class="steps steps-horizontal steps-counter m-0">
                @foreach ($kycShop['steps'] as $step)
                    <li class="step-item {{ $step['done'] ? 'active' : '' }}">
                        <div class="h4 m-0">{{ $step['label'] }}</div>
                        <div class="text-secondary small">{{ $step['done'] ? 'Selesai' : 'Menunggu' }}</div>
                    </li>
                @endforeach
            </ul>
        </div></x-admin.card>
    </div>
    @endif
    @if ($trustShop)
    <div class="col-12 col-md-5">
        <x-admin.card><div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Skor kepercayaan publik</span>
                <span class="badge bg-{{ $trustShop['badge'] }}-lt">{{ $trustShop['skor'] }}/100 · {{ $trustShop['label'] }}</span>
            </div>
            <div class="progress my-2" role="progressbar" aria-valuenow="{{ $trustShop['skor'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Skor kepercayaan toko">
                <div class="progress-bar bg-{{ $trustShop['badge'] }}" style="width: {{ $trustShop['skor'] }}%"></div>
            </div>
            <dl class="row small mb-0">
                <dt class="col-6 text-secondary fw-normal">Rating (30%)</dt><dd class="col-6 text-end m-0">+{{ $trustShop['rincian']['rating'] }}</dd>
                <dt class="col-6 text-secondary fw-normal">Pemenuhan (30%)</dt><dd class="col-6 text-end m-0">+{{ $trustShop['rincian']['fulfillment'] }}</dd>
                <dt class="col-6 text-secondary fw-normal">Respons (25%)</dt><dd class="col-6 text-end m-0">+{{ $trustShop['rincian']['respons'] }}</dd>
                <dt class="col-6 text-secondary fw-normal">Umur toko (15%)</dt><dd class="col-6 text-end m-0">+{{ $trustShop['rincian']['umur_toko'] }}</dd>
            </dl>
        </div></x-admin.card>
    </div>
    @endif
</div>
@endif
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
