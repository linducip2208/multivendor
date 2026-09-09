@extends('layouts.admin')
@section('title', '.env Settings')
@section('content')
<div class="mb-4"><h4 class="fw-bold"><i class="fas fa-cog me-2"></i>Environment Settings (.env)</h4></div>
<div class="card border-0 rounded-4 shadow-sm">
    <div class="card-body">
        <div class="alert alert-warning small"><i class="fas fa-exclamation-triangle me-2"></i>Hanya konfigurasi operasional yang aman untuk diubah di sini. Secret, password, dan koneksi database tidak pernah ditampilkan atau dapat ditulis dari browser. Backup dibuat otomatis.</div>
        <form method="POST" action="{{ route('admin.system.env-settings-update') }}">
            @csrf @method('PUT')
            <div class="row g-3">
                @foreach($settings as $key => $value)
                    <div class="col-md-6">
                        <label class="form-label font-monospace small">{{ $key }}</label>
                        <input name="settings[{{ $key }}]" value="{{ old('settings.'.$key, $value) }}" class="form-control font-monospace" maxlength="2048">
                    </div>
                @endforeach
            </div>
            <button type="submit" class="btn btn-primary mt-3" onclick="return confirm('Yakin ubah .env?')"><i class="fas fa-save me-2"></i>Simpan & Clear Cache</button>
        </form>
    </div>
</div>
@endsection
