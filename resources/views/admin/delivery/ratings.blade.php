@extends('layouts.admin')
@section('title', 'Delivery Ratings')
@section('content')
<div class="mb-4"><h4 class="fw-bold"><x-admin.icon name="star" :size="16" class="me-2" />Rating Kurir</h4></div>
<x-admin.card :padding="false">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th class="text-uppercase small">KURIR</th><th class="text-uppercase small">CUSTOMER</th><th class="text-uppercase small">ORDER</th><th class="text-uppercase small">RATING</th><th class="text-uppercase small">REVIEW</th></tr></thead>
            <tbody>
                @forelse($ratings as $r)
                <tr><td><a href="{{ route('admin.delivery.rating-report', $r->deliveryMan) }}">{{ $r->deliveryMan->name ?? '-' }}</a></td><td>{{ $r->customer->name ?? '-' }}</td><td><small>{{ $r->order->order_number ?? '-' }}</small></td><td><span class="text-warning">@for($i=0;$i<$r->rating;$i++)<x-admin.icon name="star" :size="16" />@endfor @for($i=$r->rating;$i<5;$i++)<x-admin.icon name="star" :size="16" />@endfor</span></td><td><small>{{ \Illuminate\Support\Str::limit($r->review ?? '', 80) }}</small></td></tr>
                @empty
                <tr><td colspan="5" class="text-center py-5 text-muted">Belum ada rating.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.card>
<div class="mt-3"><x-admin.pagination :paginator="$ratings" size="sm" /></div>
@endsection
