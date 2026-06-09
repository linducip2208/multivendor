@extends('layouts.admin')
@section('title', 'Edit Provider')
@section('content')
<div class="mb-4"><a href="{{ route('admin.providers.index') }}" class="small"><i class="fas fa-arrow-left me-1"></i>Kembali</a><h4 class="fw-bold mt-2">Edit Provider: {{ $provider->name }}</h4></div>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
<form method="POST" action="{{ route('admin.providers.update', $provider) }}">@csrf @method('PUT')
<div class="row g-3">
    <div class="col-md-6"><label class="fw-medium">Nama Provider <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="{{ old('name', $provider->name) }}" required></div>
    <div class="col-md-3"><label class="fw-medium">Format API <span class="text-danger">*</span></label><input type="text" name="api_format" class="form-control" value="{{ old('api_format', $provider->api_format) }}" required></div>
    <div class="col-md-3"><label class="fw-medium">Tipe</label><input type="text" class="form-control" value="{{ ucfirst($provider->type) }}" disabled></div>
    <div class="col-12"><label class="fw-medium">Base URL</label><input type="text" name="base_url" class="form-control" value="{{ old('base_url', $provider->base_url) }}"></div>
    <div class="col-md-6"><label class="fw-medium">API Key</label><input type="text" name="api_key" class="form-control" placeholder="Kosongkan jika tidak diubah"><small class="text-muted">Key tersimpan: {{ $provider->getMaskedKey() }}</small></div>
    <div class="col-md-6"><label class="fw-medium">Secret Key</label><input type="text" name="api_secret" class="form-control" placeholder="Kosongkan jika tidak diubah"></div>
    <div class="col-12"><label class="fw-medium">Extra Headers (JSON)</label><textarea name="extra_headers" class="form-control font-monospace small" rows="3">{{ old('extra_headers', json_encode($provider->extra_headers, JSON_PRETTY_PRINT)) }}</textarea></div>
    <div class="col-12"><label class="fw-medium">Config Tambahan (JSON)</label><textarea name="config" class="form-control font-monospace small" rows="3">{{ old('config', json_encode($provider->config, JSON_PRETTY_PRINT)) }}</textarea></div>
    <div class="col-12"><label class="fw-medium">Deskripsi</label><textarea name="description" class="form-control" rows="2">{{ old('description', $provider->description) }}</textarea></div>
    <div class="col-md-4"><div class="form-check form-switch mt-4"><input type="checkbox" name="is_active" class="form-check-input" id="isActive" value="1" {{ $provider->is_active ? 'checked' : '' }}><label class="form-check-label fw-medium" for="isActive">Provider Aktif</label></div></div>
    <div class="col-12"><button class="btn btn-primary"><i class="fas fa-save me-2"></i>Perbarui</button></div>
</div>
</form></div></div>
@endsection
