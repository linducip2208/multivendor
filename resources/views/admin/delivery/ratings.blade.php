@extends('layouts.admin')
@section('title', 'Delivery Ratings')
@section('content')
<div class="mb-4"><h4 class="fw-bold"><i class="fas fa-star me-2"></i>Rating Kurir</h4></div>
<div class="card border-0 rounded-4 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th class="text-uppercase small">KURIR</th><th class="text-uppercase small">CUSTOMER</th><th class="text-uppercase small">ORDER</th><th class="text-uppercase small">RATING</th><th class="text-uppercase small">REVIEW</th></tr></thead>
            <tbody>
                @forelse($ratings as $r)
                <tr><td><a href="{{ route('admin.delivery.rating-report', $r->deliveryMan) }}">{{ $r->deliveryMan->name ?? '-' }}</a></td><td>{{ $r->customer->name ?? '-' }}</td><td><small>{{ $r->order->order_number ?? '-' }}</small></td><td><span class="text-warning">@for($i=0;$i<$r->rating;$i++)<i class="fas fa-star"></i>@endfor @for($i=$r->rating;$i<5;$i++)<i class="far fa-star"></i>@endfor</span></td><td><small>{{ \Illuminate\Support\Str::limit($r->review, 80) }}</small></td></tr>
                @empty
                <tr><td colspan="5" class="text-center py-5 text-muted">Belum ada rating.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $ratings->links('vendor.pagination.bootstrap') }}</div>
@endsection
