@extends('layouts.admin')
@section('title', 'Edit Banner')
@section('content')
<div class="mb-4"><a href="{{ route('admin.banners.index') }}" class="small"><i class="fas fa-arrow-left me-1"></i>Kembali</a><h4 class="fw-bold mt-2">Edit Banner: {{ $banner->title }}</h4></div>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
<form method="POST" action="{{ route('admin.banners.update', $banner) }}">@csrf @method('PUT')
<div class="row g-3">
    <div class="col-md-6"><label class="fw-medium">Judul</label><input type="text" name="title" class="form-control" value="{{ old('title', $banner->title) }}" required></div>
    <div class="col-md-6"><label class="fw-medium">Subtitle</label><input type="text" name="subtitle" class="form-control" value="{{ old('subtitle', $banner->subtitle) }}"></div>
    <div class="col-md-6"><label class="fw-medium">URL Gambar</label><input type="text" name="image" class="form-control" value="{{ old('image', $banner->image) }}"></div>
    <div class="col-md-6"><label class="fw-medium">Link</label><input type="text" name="link" class="form-control" value="{{ old('link', $banner->link) }}"></div>
    <div class="col-md-3"><label class="fw-medium">Posisi</label><select name="position" class="form-select"><option value="hero" {{ $banner->position==='hero'?'selected' : '' }}>Hero</option><option value="sidebar" {{ $banner->position==='sidebar'?'selected' : '' }}>Sidebar</option><option value="footer" {{ $banner->position==='footer'?'selected' : '' }}>Footer</option><option value="popup" {{ $banner->position==='popup'?'selected' : '' }}>Popup</option></select></div>
    <div class="col-md-2"><label class="fw-medium">Urutan</label><input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $banner->sort_order) }}" min="0"></div>
    <div class="col-md-2"><div class="form-check mt-4"><input type="checkbox" name="status" class="form-check-input" id="st" value="1" {{ $banner->status?'checked' : '' }}><label for="st" class="fw-medium">Aktif</label></div></div>
    <div class="col-12"><button class="btn btn-primary"><i class="fas fa-save me-2"></i>Perbarui</button></div>
</div>
</form></div></div>
@endsection
