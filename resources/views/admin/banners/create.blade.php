@extends('layouts.admin')
@section('title', 'Tambah Banner')
@section('content')
<div class="mb-4"><a href="{{ route('admin.banners.index') }}" class="small"><x-admin.icon name="arrow-left" :size="16" class="me-1" />Kembali</a><h4 class="fw-bold mt-2">Tambah Banner</h4></div>
<x-admin.card :padding="false"><div class="card-body p-4">
<form method="POST" action="{{ route('admin.banners.store') }}">@csrf
<div class="row g-3">
    <div class="col-md-6"><label class="fw-medium">Judul <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" value="{{ old('title') }}" required></div>
    <div class="col-md-6"><label class="fw-medium">Subjudul</label><input type="text" name="subtitle" class="form-control" value="{{ old('subtitle') }}"></div>
    <div class="col-md-6"><label class="fw-medium" for="banner-image">URL Gambar <span class="text-danger">*</span></label><div class="input-group"><input type="text" name="image" id="banner-image" class="form-control" value="{{ old('image') }}" placeholder="/img/uploads/… atau https://…" required><x-admin.media-picker target="banner-image" preview="banner-image-preview" /></div><img id="banner-image-preview" src="{{ old('image') }}" alt="" class="rounded border mt-2 {{ old('image') ? '' : 'd-none' }}" style="max-height:120px;"></div>
    <div class="col-md-6"><label class="fw-medium">Tautan Tujuan</label><input type="text" name="link" class="form-control" value="{{ old('link') }}" placeholder="https://..."></div>
    <div class="col-md-3"><label class="fw-medium">Posisi</label><select name="position" class="form-select"><option value="hero">Hero (Atas)</option><option value="sidebar">Sidebar</option><option value="footer">Footer</option><option value="popup">Popup</option></select></div>
    <div class="col-md-2"><label class="fw-medium">Urutan</label><input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0"></div>
    <div class="col-md-3"><label class="fw-medium">Kunci Eksperimen</label><input type="text" name="experiment_key" class="form-control" value="{{ old('experiment_key') }}" maxlength="80" placeholder="mis. hero-utama"><small class="text-muted">Banner se-grup diuji A/B.</small></div>
    <div class="col-md-2"><label class="fw-medium">Bobot</label><input type="number" name="weight" class="form-control" value="{{ old('weight', 50) }}" min="1" max="100"></div>
    <div class="col-md-2"><div class="form-check mt-4"><input type="checkbox" name="status" class="form-check-input" id="st" value="1" checked><label for="st" class="fw-medium">Aktif</label></div></div>
    <div class="col-md-3"><label class="fw-medium">Tayang mulai</label><input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at') }}"></div>
    <div class="col-md-3"><label class="fw-medium">Tayang berakhir</label><input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at') }}"></div>
    <div class="col-12"><button class="btn btn-primary"><x-admin.icon name="check" :size="16" class="me-2" />Simpan</button></div>
</div>
</form></div></x-admin.card>
@endsection
