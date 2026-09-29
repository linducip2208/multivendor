@extends('layouts.admin')
@section('title', 'Maintenance')
@section('content')
<h4 class="fw-bold mb-3"><x-admin.icon name="settings" :size="16" class="me-2 text-danger" /> Maintenance</h4>
<div class="row g-4">
    <div class="col-md-6"><x-admin.card :padding="false"><div class="card-body p-4">
        <h6 class="fw-bold mb-3"><x-admin.icon name="plug" :size="16" class="me-2" />Maintenance Mode</h6>
        <p class="text-muted small">Saat maintenance mode aktif, semua pengunjung melihat halaman maintenance.</p>
        <form action="{{ route('admin.maintenance.toggle') }}" method="POST">@csrf
            @if(app()->isDownForMaintenance())
            <button class="btn btn-success w-100"><x-admin.icon name="check" :size="16" class="me-2" />Nonaktifkan Maintenance</button>
            @else
            <button class="btn btn-danger w-100"><x-admin.icon name="minus" :size="16" class="me-2" />Aktifkan Maintenance</button>
            @endif
        </form>
    </div></x-admin.card></div>
    <div class="col-md-6"><x-admin.card :padding="false"><div class="card-body p-4">
        <h6 class="fw-bold mb-3"><x-admin.icon name="database" :size="16" class="me-2" />Clear Cache</h6>
        <form action="{{ route('admin.maintenance.cache') }}" method="POST">@csrf
            <button class="btn btn-outline-warning w-100"><x-admin.icon name="refresh" :size="16" class="me-2" />Clear All Cache</button>
        </form>
    </div></x-admin.card></div>
</div>
@endsection
