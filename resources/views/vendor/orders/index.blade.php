@extends('layouts.vendor')

@section('title', 'Pesanan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-shopping-cart me-2 text-primary"></i> Pesanan</h4>
</div>

<div class="row g-3 mb-4">
    @php
    $statuses = ['pending' => 'warning', 'confirmed' => 'info', 'processing' => 'primary', 'shipped' => 'indigo', 'delivered' => 'success', 'canceled' => 'danger'];
    @endphp
    @foreach($statuses as $key => $color)
    <div class="col-4 col-md-2">
        <a href="?status={{ $key }}" class="text-decoration-none">
            <div class="card border-0 rounded-4 shadow-sm text-center p-3 {{ request('status') === $key ? 'border border-2 border-'.$color : '' }}">
                <div class="fw-bold fs-5 text-{{ $color }}">{{ $statusCounts[$key] ?? 0 }}</div>
                <small class="text-muted">{{ ucfirst($key) }}</small>
            </div>
        </a>
    </div>
    @endforeach
</div>

<div class="card border-0 rounded-4 shadow-sm">
    <div class="p-3 border-bottom">
        <form method="GET" class="row g-2">
            <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Cari nomor order..." value="{{ request('search') }}"></div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="fas fa-search me-1"></i> Cari</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>Order</th><th>Pelanggan</th><th>Total</th><th>Status</th><th>Tanggal</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                <tr>
                    <td class="fw-semibold">{{ $order->order_number }}</td>
                    <td>{{ $order->customer->name ?? '-' }}</td>
                    <td>Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                    <td><span class="badge bg-{{ $statuses[$order->order_status] }}-subtle text-{{ $statuses[$order->order_status] }}">{{ ucfirst($order->order_status) }}</span></td>
                    <td class="small text-muted">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                    <td><a href="{{ route('vendor.orders.show', $order) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada pesanan</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())<div class="p-3">{{ $orders->links() }}</div>@endif
</div>
@endsection
