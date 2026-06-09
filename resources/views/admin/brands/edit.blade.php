@extends('layouts.admin')
@section('title', 'Edit Brand')
@section('content')
<div class="mb-4"><a href="{{ route('admin.brands.index') }}" class="small"><i class="fas fa-arrow-left me-1"></i>Kembali</a><h4 class="fw-bold mt-2">Edit Brand: {{ $brand->name }}</h4></div>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
<form method="POST" action="{{ route('admin.brands.update', $brand) }}">@csrf @method('PUT')
<div class="row g-3">
    <div class="col-md-6"><label class="fw-medium">Nama Brand <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="{{ old('name', $brand->name) }}" required></div>
    <div class="col-md-6"><label class="fw-medium">Status</label><select name="status" class="form-select"><option value="1" {{ $brand->status ? 'selected' : '' }}>Aktif</option><option value="0" {{ !$brand->status ? 'selected' : '' }}>Nonaktif</option></select></div>
    <div class="col-12"><label class="fw-medium">Deskripsi</label><textarea name="description" class="form-control" rows="3">{{ old('description', $brand->description) }}</textarea></div>
    <div class="col-12"><button class="btn btn-primary"><i class="fas fa-save me-2"></i>Perbarui</button></div>
</div>
</form></div></div>
@endsection
