@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Dashboard</h4>
        <p class="text-muted small mb-0">Ringkasan platform multivendor</p>
    </div>
    <span class="text-muted small">{{ now()->translatedFormat('l, d F Y') }}</span>
</div>

{{-- Stats Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl">
        <div class="card card-stat p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value text-indigo">{{ number_format($stats['total_vendors']) }}</div>
                    <div class="stat-label">Vendor</div>
                </div>
                <span class="badge bg-indigo-light text-indigo rounded-3 p-2">
                    <i class="fas fa-store"></i>
                </span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card card-stat p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value text-success">{{ number_format($stats['total_customers']) }}</div>
                    <div class="stat-label">Pelanggan</div>
                </div>
                <span class="badge bg-success-light text-success rounded-3 p-2">
                    <i class="fas fa-users"></i>
                </span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card card-stat p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value text-warning">{{ number_format($stats['total_products']) }}</div>
                    <div class="stat-label">Produk</div>
                </div>
                <span class="badge bg-warning-light text-warning rounded-3 p-2">
                    <i class="fas fa-box"></i>
                </span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card card-stat p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value text-info">{{ number_format($stats['total_orders']) }}</div>
                    <div class="stat-label">Pesanan</div>
                </div>
                <span class="badge bg-info-light text-info rounded-3 p-2">
                    <i class="fas fa-shopping-cart"></i>
                </span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card card-stat p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value text-primary">Rp {{ number_format($stats['total_revenue'], 0, ',', '.') }}</div>
                    <div class="stat-label">Pendapatan</div>
                </div>
                <span class="badge bg-primary-light text-primary rounded-3 p-2">
                    <i class="fas fa-money-bill-wave"></i>
                </span>
            </div>
        </div>
    </div>
</div>

{{-- Alert Cards --}}
<div class="row g-3 mb-4">
    @if($stats['pending_shops'] > 0)
    <div class="col-md-4">
        <div class="card border-warning border-2 rounded-4">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="fas fa-clock fa-2x text-warning"></i>
                <div>
                    <div class="fw-bold">{{ $stats['pending_shops'] }} Toko</div>
                    <small class="text-muted">Menunggu persetujuan</small>
                </div>
            </div>
        </div>
    </div>
    @endif
    @if($stats['pending_products'] > 0)
    <div class="col-md-4">
        <div class="card border-warning border-2 rounded-4">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="fas fa-box-open fa-2x text-warning"></i>
                <div>
                    <div class="fw-bold">{{ $stats['pending_products'] }} Produk</div>
                    <small class="text-muted">Menunggu persetujuan</small>
                </div>
            </div>
        </div>
    </div>
    @endif
    @if($stats['pending_orders'] > 0)
    <div class="col-md-4">
        <div class="card border-danger border-2 rounded-4">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                <div>
                    <div class="fw-bold">{{ $stats['pending_orders'] }} Pesanan</div>
                    <small class="text-muted">Perlu diproses</small>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

{{-- Recent Orders + Recent Shops --}}
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-3 px-3 d-flex justify-content-between">
                <h6 class="fw-bold mb-0"><i class="fas fa-shopping-cart me-2 text-primary"></i> Pesanan Terbaru</h6>
                <a href="#" class="text-decoration-none small">Lihat semua <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3 text-uppercase small">Order</th>
                                <th class="text-uppercase small">Pelanggan</th>
                                <th class="text-uppercase small">Toko</th>
                                <th class="text-uppercase small">Total</th>
                                <th class="text-uppercase small">Status</th>
                                <th class="text-uppercase small">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentOrders as $order)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $order->order_number }}</td>
                                <td>{{ $order->customer->name ?? '-' }}</td>
                                <td>{{ $order->shop->name ?? '-' }}</td>
                                <td>Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                                <td>
                                    @php
                                        $badges = [
                                            'pending' => 'warning', 'confirmed' => 'info',
                                            'processing' => 'primary', 'shipped' => 'indigo',
                                            'delivered' => 'success', 'canceled' => 'danger',
                                        ];
                                    @endphp
                                    <span class="badge bg-{{ $badges[$order->order_status] ?? 'secondary' }}-subtle text-{{ $badges[$order->order_status] ?? 'secondary' }}">
                                        {{ ucfirst($order->order_status) }}
                                    </span>
                                </td>
                                <td class="small text-muted">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada pesanan</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-3 px-3">
                <h6 class="fw-bold mb-0"><i class="fas fa-store me-2 text-success"></i> Toko Baru</h6>
            </div>
            <div class="card-body">
                @forelse($recentShops as $shop)
                <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                    <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                        <i class="fas fa-store text-primary"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold small">{{ $shop->name }}</div>
                        <div class="text-muted" style="font-size:.75rem;">{{ $shop->vendor->name ?? '-' }}</div>
                    </div>
                    <span class="badge bg-{{ $shop->status === 'active' ? 'success' : ($shop->status === 'pending' ? 'warning' : 'danger') }}-subtle">
                        {{ $shop->status }}
                    </span>
                </div>
                @empty
                <p class="text-muted text-center py-3">Belum ada toko terdaftar</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
