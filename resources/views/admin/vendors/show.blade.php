@extends('layouts.admin')

@section('title', 'Detail Vendor')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.vendors.index') }}" class="text-decoration-none small">
        <i class="fas fa-arrow-left me-1"></i> Kembali
    </a>
    <div class="d-flex justify-content-between align-items-center mt-2">
        <h4 class="fw-bold mb-0">{{ $shop->name }}</h4>
        <div>
            <a href="{{ route('admin.vendors.edit', $shop) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-edit me-1"></i> Edit</a>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body text-center p-4">
                <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px;">
                    <i class="fas fa-store fa-2x text-primary"></i>
                </div>
                <h5 class="fw-bold">{{ $shop->name }}</h5>
                <p class="text-muted small">{{ $shop->slug }}</p>
                @php $badges = ['pending' => 'warning', 'active' => 'success', 'suspended' => 'danger', 'rejected' => 'dark']; @endphp
                <span class="badge bg-{{ $badges[$shop->status] ?? 'secondary' }}-subtle text-{{ $badges[$shop->status] ?? 'secondary' }} px-3 py-2">
                    {{ ucfirst($shop->status) }}
                </span>
            </div>
            <hr class="my-0">
            <div class="card-body p-3">
                <div class="mb-3">
                    <small class="text-muted">Vendor</small>
                    <div class="fw-medium">{{ $shop->vendor->name ?? '-' }}</div>
                    <small>{{ $shop->vendor->email ?? '-' }}</small>
                </div>
                <div class="mb-3">
                    <small class="text-muted">Kontak</small>
                    <div>{{ $shop->phone ?? '-' }}</div>
                </div>
                <div class="mb-3">
                    <small class="text-muted">Alamat</small>
                    <div>{{ $shop->address ?? '-' }}</div>
                </div>
                <div class="mb-3">
                    <small class="text-muted">Komisi</small>
                    <div>
                        @if($shop->commission_type === 'percentage')
                            <span class="fw-bold">{{ $shop->commission_value }}%</span> per transaksi
                        @else
                            <span class="fw-bold">Rp {{ number_format($shop->commission_value, 0, ',', '.') }}</span> per transaksi
                        @endif
                    </div>
                </div>
                <div>
                    <small class="text-muted">Bergabung</small>
                    <div>{{ $shop->created_at->translatedFormat('d F Y') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 rounded-4 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-3 px-3">
                <h6 class="fw-bold mb-0"><i class="fas fa-box me-2"></i> Produk Terbaru</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Produk</th><th>Harga</th><th>Stok</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse($shop->products as $product)
                        <tr>
                            <td class="fw-medium">{{ $product->name }}</td>
                            <td>Rp {{ number_format($product->price, 0, ',', '.') }}</td>
                            <td>{{ $product->current_stock }}</td>
                            <td><span class="badge bg-{{ $product->status === 'approved' ? 'success' : 'warning' }}-subtle">{{ $product->status }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center py-3 text-muted">Belum ada produk</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-3 px-3">
                <h6 class="fw-bold mb-0"><i class="fas fa-shopping-cart me-2"></i> Pesanan Terbaru</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Order</th><th>Total</th><th>Status</th><th>Tanggal</th></tr>
                    </thead>
                    <tbody>
                        @forelse($shop->orders as $order)
                        <tr>
                            <td class="fw-semibold">{{ $order->order_number }}</td>
                            <td>Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                            <td><span class="badge bg-info-subtle text-info">{{ $order->order_status }}</span></td>
                            <td class="small">{{ $order->created_at->format('d/m/Y') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center py-3 text-muted">Belum ada pesanan</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
