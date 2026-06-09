@extends('layouts.admin')
@section('title', 'Tambah Banner')
@section('content')
<div class="mb-4"><a href="{{ route('admin.banners.index') }}" class="small"><i class="fas fa-arrow-left me-1"></i>Kembali</a><h4 class="fw-bold mt-2">Tambah Banner</h4></div>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
<form method="POST" action="{{ route('admin.banners.store') }}">@csrf
<div class="row g-3">
    <div class="col-md-6"><label class="fw-medium">Judul <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" value="{{ old('title') }}" required></div>
    <div class="col-md-6"><label class="fw-medium">Subtitle</label><input type="text" name="subtitle" class="form-control" value="{{ old('subtitle') }}"></div>
    <div class="col-md-6"><label class="fw-medium">URL Gambar <span class="text-danger">*</span></label><input type="text" name="image" class="form-control" value="{{ old('image') }}" placeholder="https://..." required></div>
    <div class="col-md-6"><label class="fw-medium">Link Tujuan</label><input type="text" name="link" class="form-control" value="{{ old('link') }}" placeholder="https://..."></div>
    <div class="col-md-3"><label class="fw-medium">Posisi</label><select name="position" class="form-select"><option value="hero">Hero (Atas)</option><option value="sidebar">Sidebar</option><option value="footer">Footer</option><option value="popup">Popup</option></select></div>
    <div class="col-md-2"><label class="fw-medium">Urutan</label><input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0"></div>
    <div class="col-md-2"><div class="form-check mt-4"><input type="checkbox" name="status" class="form-check-input" id="st" value="1" checked><label for="st" class="fw-medium">Aktif</label></div></div>
    <div class="col-12"><button class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button></div>
</div>
</form></div></div>
@endsection
