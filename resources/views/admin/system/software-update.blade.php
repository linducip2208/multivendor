@extends('layouts.admin')
@section('title', 'Software Update')
@section('content')
<div class="mb-4"><h4 class="fw-bold"><i class="fas fa-sync-alt me-2"></i>Software Update</h4></div>
<div class="card border-0 rounded-4 shadow-sm" style="max-width:500px">
    <div class="card-body">
        <div class="mb-3"><label class="fw-medium">Versi Saat Ini</label><div class="fs-4 fw-bold">{{ $currentVersion }}</div></div>
        <div class="mb-3"><label class="fw-medium">Terakhir Cek Update</label><div>{{ $lastUpdate }}</div></div>
        <form method="POST" action="{{ route('admin.system.check-update') }}">
            @csrf
            <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt me-2"></i>Cek Update Sekarang</button>
        </form>
        <div class="mt-3 small text-muted">Update manual: git pull origin main && composer install && php artisan migrate && php artisan optimize</div>
    </div>
</div>
@endsection
