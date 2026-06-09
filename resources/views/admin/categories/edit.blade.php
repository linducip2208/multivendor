@extends('layouts.admin')

@section('title', 'Edit Kategori')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.categories.index') }}" class="text-decoration-none small"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
    <h4 class="fw-bold mt-2 mb-1">Edit Kategori: {{ $category->name }}</h4>
</div>

<div class="card border-0 rounded-4 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.categories.update', $category) }}">
            @csrf @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-medium">Parent Kategori</label>
                    <select name="parent_id" class="form-select">
                        <option value="">-- Kategori Utama --</option>
                        @foreach($parents as $p)
                            <option value="{{ $p->id }}" {{ old('parent_id', $category->parent_id) == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Nama <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $category->name) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Icon</label>
                    <input type="text" name="icon" class="form-control" value="{{ old('icon', $category->icon) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Status</label>
                    <select name="status" class="form-select">
                        <option value="1" {{ old('status', $category->status) == '1' ? 'selected' : '' }}>Aktif</option>
                        <option value="0" {{ old('status', $category->status) == '0' ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-medium">Deskripsi</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description', $category->description) }}</textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i> Perbarui</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
