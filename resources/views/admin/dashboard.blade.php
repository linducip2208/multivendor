@extends('layouts.admin')

@section('title', 'Dasbor')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Dasbor</h4>
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
                    <x-admin.icon name="store" :size="16" />
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
                    <x-admin.icon name="users" :size="16" />
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
                    <x-admin.icon name="box" :size="16" />
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
                    <x-admin.icon name="shopping-cart" :size="16" />
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
                    <x-admin.icon name="wallet" :size="16" />
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
                <x-admin.icon name="clock" :size="32" class="text-warning" />
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
                <x-admin.icon name="box" :size="32" class="text-warning" />
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
                <x-admin.icon name="alert-triangle" :size="32" class="text-danger" />
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
        <x-admin.card :padding="false">
            <div class="card-header bg-transparent border-0 pt-3 px-3 d-flex justify-content-between">
                <h6 class="fw-bold mb-0"><x-admin.icon name="shopping-cart" :size="16" class="me-2 text-primary" /> Pesanan Terbaru</h6>
                <a href="#" class="text-decoration-none small">Lihat semua <x-admin.icon name="arrow-right" :size="16" class="ms-1" /></a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3 text-uppercase small">Pesanan</th>
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
                                    <x-admin.badge :color="$badges[$order->order_status] ?? 'secondary'" :text="ucfirst($order->order_status)" />
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
        </x-admin.card>
    </div>
    <div class="col-lg-4">
        <x-admin.card :padding="false">
            <div class="card-header bg-transparent border-0 pt-3 px-3">
                <h6 class="fw-bold mb-0"><x-admin.icon name="store" :size="16" class="me-2 text-success" /> Toko Baru</h6>
            </div>
            <div class="card-body">
                @forelse($recentShops as $shop)
                <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                    <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                        <x-admin.icon name="store" :size="16" class="text-primary" />
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold small">{{ $shop->name }}</div>
                        <div class="text-muted" style="font-size:.75rem;">{{ $shop->vendor->name ?? '-' }}</div>
                    </div>
                    <x-admin.badge :color="$shop->status === 'active' ? 'success' : ($shop->status === 'pending' ? 'warning' : 'danger')" :text="$shop->status" />
                </div>
                @empty
                <x-admin.empty-state title="Belum ada toko terdaftar" compact />
                @endforelse
            </div>
        </x-admin.card>
    </div>
</div>
@endsection
