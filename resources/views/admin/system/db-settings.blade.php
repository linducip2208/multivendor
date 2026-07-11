@extends('layouts.admin')
@section('title', 'Database Settings')
@section('content')
<div class="mb-4"><h4 class="fw-bold"><i class="fas fa-database me-2"></i>Database Settings</h4></div>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card card-stat"><div class="stat-label">Total Tabel</div><div class="stat-value">{{ count($tables) }}</div></div></div>
    <div class="col-md-3"><div class="card card-stat"><div class="stat-label">Ukuran DB</div><div class="stat-value">{{ number_format($dbSize / 1024 / 1024, 1) }} MB</div></div></div>
    <div class="col-md-3"><div class="card card-stat"><div class="stat-label">Connection</div><div class="stat-value small">{{ config('database.default') }}</div></div></div>
    <div class="col-md-3"><div class="card card-stat"><div class="stat-label">Action</div><form method="POST" action="{{ route('admin.system.db-optimize') }}">@csrf <button class="btn btn-outline-primary btn-sm">Optimize Tables</button></form></div></div>
</div>
<div class="card border-0 rounded-4 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th class="text-uppercase small">TABEL</th><th class="text-uppercase small">ROWS</th><th class="text-uppercase small">SIZE</th><th class="text-uppercase small">AUTO INCREMENT</th><th class="text-uppercase small">ENGINE</th></tr></thead>
            <tbody>
                @foreach($tables as $t)
                <tr><td class="fw-medium font-monospace small">{{ $t->Name ?? $t->name }}</td><td>{{ number_format($t->Rows ?? $t->rows ?? 0) }}</td><td>{{ round(($t->Data_length + $t->Index_length) / 1024, 1) }} KB</td><td>{{ $t->Auto_increment ?? '-' }}</td><td>{{ $t->Engine ?? $t->engine ?? '-' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
