@extends('layouts.admin')
@section('title', 'Kurir')
@section('content')
<h4 class="fw-bold mb-1"><i class="fas fa-truck me-2 text-warning"></i> Kurir / Pengiriman</h4>
<p class="text-muted small mb-3">Kelola kurir dan tracking pengiriman. <a href="{{ route('admin.providers.index') }}?type=shipping">Setup shipping provider di Integrasi <i class="fas fa-plug"></i></a></p>
<div class="card border-0 rounded-4 shadow-sm">
    <div class="card-body text-center py-5">
        <i class="fas fa-truck fa-4x text-muted mb-3 opacity-25"></i>
        <h5>Manajemen Kurir</h5>
        <p class="text-muted">Untuk menambah layanan pengiriman, tambahkan provider shipping di menu <strong>Integrasi</strong>.</p>
        <a href="{{ route('admin.providers.create') }}" class="btn btn-primary"><i class="fas fa-plus me-2"></i> Tambah Shipping Provider</a>
    </div>
</div>
@endsection
