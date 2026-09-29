@extends('layouts.vendor')
@section('title', 'Orientasi - Langkah 4')
@section('content')
<div style="max-width:500px;margin:0 auto">
    <div class="text-center mb-4"><div class="mb-2"><x-admin.badge color="primary">Langkah 4/4</x-admin.badge></div><h4 class="fw-bold">Logo & Banner</h4><p class="text-muted small">Unggah logo dan banner toko Anda</p></div>
    <form method="POST" action="{{ route('vendor.onboarding.step4.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="mb-3"><label class="form-label fw-medium">Logo Toko (200x200)</label><input type="file" name="logo" class="form-control" accept="image/*"></div>
        <div class="mb-3"><label class="form-label fw-medium">Banner Toko (1200x400)</label><input type="file" name="banner" class="form-control" accept="image/*"></div>
        <div class="d-flex justify-content-between">
            <a href="{{ route('vendor.onboarding.step3') }}" class="btn btn-outline-secondary"><x-admin.icon name="arrow-left" :size="16" class="me-1" />Sebelumnya</a>
            <button type="submit" class="btn btn-success"><x-admin.icon name="check" :size="16" class="me-2" />Selesai Penyiapan</button>
        </div>
    </form>
</div>
@endsection
