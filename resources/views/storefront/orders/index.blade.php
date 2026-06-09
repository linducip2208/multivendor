@extends('layouts.storefront')
@section('title', 'Pesanan Saya')

@section('content')
<div class="container">
    <h4 class="fw-bold mb-4"><i class="fas fa-list-alt me-2 text-primary"></i> Pesanan Saya</h4>

    @if($orders->count() > 0)
    <div class="row g-3">
        @foreach($orders as $order)
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <a href="{{ route('orders.show', $order) }}" class="fw-bold text-decoration-none">{{ $order->order_number }}</a>
                            <div class="small text-muted">{{ $order->shop->name ?? '' }}</div>
                            <div class="small text-muted">{{ $order->created_at->format('d M Y H:i') }}</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">Rp {{ number_format($order->total, 0, ',', '.') }}</div>
                            @php $badges = ['pending'=>'warning','confirmed'=>'info','processing'=>'primary','shipped'=>'indigo','delivered'=>'success','canceled'=>'danger']; @endphp
                            <span class="badge bg-{{ $badges[$order->order_status] }}-subtle text-{{ $badges[$order->order_status] }}">{{ ucfirst($order->order_status) }}</span>
                            <span class="badge bg-{{ $order->payment_status === 'paid' ? 'success' : 'warning' }}-subtle ms-1">{{ $order->payment_status }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    {{ $orders->links() }}
    @else
    <div class="text-center py-5">
        <i class="fas fa-receipt fa-4x text-muted mb-3 opacity-25"></i>
        <h5>Belum ada pesanan</h5>
        <a href="{{ route('products.index') }}" class="btn btn-primary mt-2">Mulai Belanja</a>
    </div>
    @endif
</div>
@endsection
