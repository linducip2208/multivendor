@extends('layouts.admin')
@section('title', 'Error Logs')
@section('content')
<div class="mb-4 d-flex justify-content-between align-items-center">
    <h4 class="fw-bold"><i class="fas fa-exclamation-triangle me-2"></i>Error Logs</h4>
    <div><a href="{{ route('admin.system.db-settings') }}" class="btn btn-sm btn-outline-secondary me-2">DB Settings</a><a href="{{ route('admin.system.env-settings') }}" class="btn btn-sm btn-outline-secondary me-2">.env</a><a href="{{ route('admin.system.software-update') }}" class="btn btn-sm btn-outline-secondary me-2">Update</a><form method="POST" action="{{ route('admin.system.error-logs-clear') }}" class="d-inline" onsubmit="return confirm('Hapus semua error logs?')">@csrf <button class="btn btn-sm btn-outline-danger">Clear Logs</button></form></div>
</div>
<div class="card border-0 rounded-4 shadow-sm">
    <div class="card-body">
        <pre style="max-height:600px;overflow-y:auto;font-size:12px;font-family:monospace;line-height:1.6;white-space:pre-wrap;word-wrap:break-word;">@forelse($logs as $log){{ $log }}
@endforeach
@empty<div class="text-center py-5 text-muted">Tidak ada error log.</div>@endforelse</pre>
    </div>
</div>
@endsection
