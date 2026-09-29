@extends('layouts.admin')
@section('title', 'Laporan Peringkat Kurir')
@section('content')
<div class="mb-4"><a href="{{ route('admin.delivery.ratings') }}" class="small"><x-admin.icon name="arrow-left" :size="16" class="me-1" />Kembali</a><h4 class="fw-bold mt-2">{{ $user->name }}</h4></div>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card card-stat"><div class="stat-label">Peringkat Rata-rata</div><div class="stat-value text-warning">★ {{ number_format($avgRating,1) }}</div></div></div>
    <div class="col-md-4"><div class="card card-stat"><div class="stat-label">Total Peringkat</div><div class="stat-value">{{ $totalRatings }}</div></div></div>
    <div class="col-md-4"><div class="card card-stat"><div class="stat-label">Pengiriman Selesai</div><div class="stat-value text-success">{{ $completedDeliveries }}</div></div></div>
</div>
<x-admin.card :padding="false">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th class="text-uppercase small">PELANGGAN</th><th class="text-uppercase small">PESANAN</th><th class="text-uppercase small">PERINGKAT</th><th class="text-uppercase small">ULASAN</th></tr></thead>
            <tbody>
                @forelse($ratings as $r)
                <tr><td>{{ $r->customer->name ?? '-' }}</td><td><small>{{ $r->order->order_number ?? '-' }}</small></td><td>@for($i=0;$i<$r->rating;$i++)★@endfor</td><td>{{ $r->review }}</td></tr>
                @empty
                <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada peringkat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.card>
<div class="mt-3"><x-admin.pagination :paginator="$ratings" size="sm" /></div>
@endsection
