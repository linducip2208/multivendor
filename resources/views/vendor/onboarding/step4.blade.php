@extends('layouts.vendor')
@section('title', 'Onboarding - Step 4')
@section('content')
<div style="max-width:500px;margin:0 auto">
    <div class="text-center mb-4"><div class="mb-2"><span class="badge bg-primary">Step 4/4</span></div><h4 class="fw-bold">Logo & Banner</h4><p class="text-muted small">Upload logo dan banner toko Anda</p></div>
    <form method="POST" action="{{ route('vendor.onboarding.step4.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="mb-3"><label class="form-label fw-medium">Logo Toko (200x200)</label><input type="file" name="logo" class="form-control" accept="image/*"></div>
        <div class="mb-3"><label class="form-label fw-medium">Banner Toko (1200x400)</label><input type="file" name="banner" class="form-control" accept="image/*"></div>
        <div class="d-flex justify-content-between">
            <a href="{{ route('vendor.onboarding.step3') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Sebelumnya</a>
            <button type="submit" class="btn btn-success"><i class="fas fa-check me-2"></i>Selesai Setup</button>
        </div>
    </form>
</div>
@endsection
