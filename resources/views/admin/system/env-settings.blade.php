@extends('layouts.admin')
@section('title', '.env Settings')
@section('content')
<div class="mb-4"><h4 class="fw-bold"><i class="fas fa-cog me-2"></i>Environment Settings (.env)</h4></div>
<div class="card border-0 rounded-4 shadow-sm">
    <div class="card-body">
        <div class="alert alert-warning small"><i class="fas fa-exclamation-triangle me-2"></i>Hati-hati! Mengubah .env bisa merusak aplikasi. Pastikan backup dulu.</div>
        <form method="POST" action="{{ route('admin.system.env-settings-update') }}">
            @csrf @method('PUT')
            <textarea name="env" class="form-control font-monospace" rows="20" style="font-size:13px;line-height:1.5;">{{ $env }}</textarea>
            <button type="submit" class="btn btn-primary mt-3" onclick="return confirm('Yakin ubah .env?')"><i class="fas fa-save me-2"></i>Simpan & Clear Cache</button>
        </form>
    </div>
</div>
@endsection
