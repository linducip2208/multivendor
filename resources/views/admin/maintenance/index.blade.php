@extends('layouts.admin')
@section('title', 'Maintenance')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-tools me-2 text-danger"></i> Maintenance</h4>
<div class="row g-4">
    <div class="col-md-6"><div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
        <h6 class="fw-bold mb-3"><i class="fas fa-power-off me-2"></i>Maintenance Mode</h6>
        <p class="text-muted small">Saat maintenance mode aktif, semua pengunjung melihat halaman maintenance.</p>
        <form action="{{ route('admin.maintenance.toggle') }}" method="POST">@csrf
            @if(app()->isDownForMaintenance())
            <button class="btn btn-success w-100"><i class="fas fa-play me-2"></i>Nonaktifkan Maintenance</button>
            @else
            <button class="btn btn-danger w-100"><i class="fas fa-pause me-2"></i>Aktifkan Maintenance</button>
            @endif
        </form>
    </div></div></div>
    <div class="col-md-6"><div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
        <h6 class="fw-bold mb-3"><i class="fas fa-database me-2"></i>Clear Cache</h6>
        <form action="{{ route('admin.maintenance.cache') }}" method="POST">@csrf
            <button class="btn btn-outline-warning w-100"><i class="fas fa-broom me-2"></i>Clear All Cache</button>
        </form>
    </div></div></div>
</div>
@endsection
