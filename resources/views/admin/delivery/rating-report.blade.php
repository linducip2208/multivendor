@extends('layouts.admin')
@section('title', 'Delivery Man Rating Report')
@section('content')
<div class="mb-4"><a href="{{ route('admin.delivery.ratings') }}" class="small"><i class="fas fa-arrow-left me-1"></i>Kembali</a><h4 class="fw-bold mt-2">{{ $user->name }}</h4></div>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card card-stat"><div class="stat-label">Rating Rata-rata</div><div class="stat-value text-warning">★ {{ number_format($avgRating,1) }}</div></div></div>
    <div class="col-md-4"><div class="card card-stat"><div class="stat-label">Total Rating</div><div class="stat-value">{{ $totalRatings }}</div></div></div>
    <div class="col-md-4"><div class="card card-stat"><div class="stat-label">Pengiriman Selesai</div><div class="stat-value text-success">{{ $completedDeliveries }}</div></div></div>
</div>
<div class="card border-0 rounded-4 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th class="text-uppercase small">CUSTOMER</th><th class="text-uppercase small">ORDER</th><th class="text-uppercase small">RATING</th><th class="text-uppercase small">REVIEW</th></tr></thead>
            <tbody>
                @forelse($ratings as $r)
                <tr><td>{{ $r->customer->name ?? '-' }}</td><td><small>{{ $r->order->order_number ?? '-' }}</small></td><td>@for($i=0;$i<$r->rating;$i++)★@endfor</td><td>{{ $r->review }}</td></tr>
                @empty
                <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada rating.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $ratings->links('vendor.pagination.bootstrap') }}</div>
@endsection
