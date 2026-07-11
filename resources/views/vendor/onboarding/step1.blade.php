@extends('layouts.vendor')
@section('title', 'Onboarding - Step 1')
@section('content')
<div style="max-width:500px;margin:0 auto">
    <div class="text-center mb-4">
        <div class="mb-2"><span class="badge bg-primary">Step 1/4</span></div>
        <h4 class="fw-bold">Info Toko</h4>
        <p class="text-muted small">Isi informasi dasar toko Anda</p>
    </div>
    <form method="POST" action="{{ route('vendor.onboarding.step1.store') }}">
        @csrf
        <div class="mb-3"><label class="form-label fw-medium">Nama Toko</label><input type="text" name="name" class="form-control" value="{{ old('name', $shop->name) }}" required></div>
        <div class="mb-3"><label class="form-label fw-medium">Deskripsi</label><textarea name="description" class="form-control" rows="3">{{ old('description', $shop->description) }}</textarea></div>
        <div class="mb-3"><label class="form-label fw-medium">Alamat</label><textarea name="address" class="form-control" rows="2" required>{{ old('address', $shop->address) }}</textarea></div>
        <div class="mb-3"><label class="form-label fw-medium">No HP</label><input type="text" name="phone" class="form-control" value="{{ old('phone', $shop->phone) }}"></div>
        <div class="d-flex justify-content-between">
            <a href="{{ route('vendor.onboarding.skip') }}" class="btn btn-outline-secondary">Skip</a>
            <button type="submit" class="btn btn-primary">Lanjut <i class="fas fa-arrow-right ms-1"></i></button>
        </div>
    </form>
</div>
@endsection
