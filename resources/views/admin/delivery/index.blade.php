@extends('layouts.admin')
@section('title', 'Kurir')
@section('content')
<h4 class="fw-bold mb-1"><x-admin.icon name="truck" :size="16" class="me-2 text-warning" /> Kurir / Pengiriman</h4>
<p class="text-muted small mb-3">Kelola kurir dan tracking pengiriman. <a href="{{ route('admin.providers.index') }}?type=shipping">Setup shipping provider di Integrasi <x-admin.icon name="plug" :size="16" /></a></p>
<x-admin.card :padding="false">
    <div class="card-body text-center py-5">
        <x-admin.icon name="truck" :size="56" class="text-muted mb-3 opacity-25" />
        <h5>Manajemen Kurir</h5>
        <p class="text-muted">Untuk menambah layanan pengiriman, tambahkan provider shipping di menu <strong>Integrasi</strong>.</p>
        <a href="{{ route('admin.providers.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" class="me-2" /> Tambah Shipping Provider</a>
    </div>
</x-admin.card>
@endsection
