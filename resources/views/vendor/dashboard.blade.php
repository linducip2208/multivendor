@extends('layouts.vendor')

@section('title', 'Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Dashboard</h4>
        <p class="text-muted small mb-0">{{ $shop->name ?? 'Toko' }}
            @if($shop->vacation_mode ?? false)
                <span class="badge bg-warning ms-2"><i class="fas fa-umbrella-beach me-1"></i> Mode Liburan</span>
            @endif
        </p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <span class="text-muted small">{{ now()->translatedFormat('l, d F Y') }}</span>
        <form action="{{ route('vendor.shop.vacation') }}" method="POST">
            @csrf @method('PUT')
            <button type="submit" class="btn btn-sm btn-{{ ($shop->vacation_mode ?? false) ? 'success' : 'outline-warning' }}">
                <i class="fas fa-{{ ($shop->vacation_mode ?? false) ? 'store' : 'umbrella-beach' }} me-1"></i>
                {{ ($shop->vacation_mode ?? false) ? 'Buka Toko' : 'Mode Liburan' }}
            </button>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl">
        <div class="card card-stat p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div><div class="stat-value text-success">{{ number_format($stats['active_products']) }}</div><div class="stat-label">Produk Aktif</div></div>
                <span class="badge bg-success-subtle text-success rounded-3 p-2"><i class="fas fa-box"></i></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card card-stat p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div><div class="stat-value text-warning">{{ number_format($stats['pending_orders']) }}</div><div class="stat-label">Pesanan Baru</div></div>
                <span class="badge bg-warning-subtle text-warning rounded-3 p-2"><i class="fas fa-shopping-cart"></i></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card card-stat p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div><div class="stat-value text-info">{{ number_format($stats['total_orders']) }}</div><div class="stat-label">Total Pesanan</div></div>
                <span class="badge bg-info-subtle text-info rounded-3 p-2"><i class="fas fa-list-alt"></i></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card card-stat p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div><div class="stat-value text-primary">Rp {{ number_format($stats['revenue'], 0, ',', '.') }}</div><div class="stat-label">Pendapatan</div></div>
                <span class="badge bg-primary-subtle text-primary rounded-3 p-2"><i class="fas fa-money-bill-wave"></i></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card card-stat p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div><div class="stat-value text-purple">Rp {{ number_format($stats['wallet_balance'], 0, ',', '.') }}</div><div class="stat-label">Saldo Wallet</div></div>
                <span class="badge bg-purple-subtle text-purple rounded-3 p-2"><i class="fas fa-wallet"></i></span>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 rounded-4 shadow-sm">
    <div class="card-header bg-transparent border-0 pt-3 px-3 d-flex justify-content-between">
        <h6 class="fw-bold mb-0"><i class="fas fa-shopping-cart me-2 text-primary"></i> Pesanan Terbaru</h6>
        <a href="{{ route('vendor.orders.index') }}" class="text-decoration-none small">Lihat semua <i class="fas fa-arrow-right ms-1"></i></a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Order</th><th>Pelanggan</th><th>Total</th><th>Status</th><th>Tgl</th></tr></thead>
            <tbody>
                @forelse($recentOrders as $o)
                <tr>
                    <td class="fw-semibold small">{{ $o->order_number }}</td>
                    <td>{{ $o->customer->name ?? '-' }}</td>
                    <td>Rp {{ number_format($o->total, 0, ',', '.') }}</td>
                    <td><span class="badge bg-{{ ['pending'=>'warning','confirmed'=>'info','processing'=>'primary','shipped'=>'indigo','delivered'=>'success','canceled'=>'danger'][$o->order_status] }}-subtle">{{ $o->order_status }}</span></td>
                    <td class="small text-muted">{{ $o->created_at->format('d/m/Y H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada pesanan</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
