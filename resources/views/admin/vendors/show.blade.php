@extends('layouts.admin')

@section('title', 'Detail Vendor')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.vendors.index') }}" class="text-decoration-none small">
        <x-admin.icon name="arrow-left" :size="16" class="me-1" /> Kembali
    </a>
    <div class="d-flex justify-content-between align-items-center mt-2">
        <h4 class="fw-bold mb-0">{{ $shop->name }}</h4>
        <div>
            <a href="{{ route('admin.vendors.edit', $shop) }}" class="btn btn-outline-primary btn-sm"><x-admin.icon name="edit" :size="16" class="me-1" /> Ubah</a>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <x-admin.card :padding="false">
            <div class="card-body text-center p-4">
                <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px;">
                    <x-admin.icon name="store" :size="32" class="text-primary" />
                </div>
                <h5 class="fw-bold">{{ $shop->name }}</h5>
                <p class="text-muted small">{{ $shop->slug }}</p>
                @php $badges = ['pending' => 'warning', 'active' => 'success', 'suspended' => 'danger', 'rejected' => 'dark']; @endphp
                <x-admin.badge :color="$badges[$shop->status] ?? 'secondary'" :text="ucfirst($shop->status)" class="px-3 py-2" />
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
                    @isset($commissionPreview)
                        <div class="mt-2 small">
                            <span class="text-muted d-block mb-1">Pratinjau komisi bertingkat</span>
                            @forelse ($commissionPreview['examples'] as $example)
                                <div class="d-flex justify-content-between">
                                    <span>Rp {{ number_format($example['price'], 0, ',', '.') }}</span>
                                    <span>Komisi Rp {{ number_format($example['commission'], 0, ',', '.') }} · Bersih Rp {{ number_format($example['net'], 0, ',', '.') }}</span>
                                </div>
                            @empty
                                <span class="text-muted">Belum ada contoh.</span>
                            @endforelse
                            @if (($commissionPreview['category_rates'] ?? []) !== [])
                                <span class="text-muted d-block mt-1">Tarif kategori: {{ count($commissionPreview['category_rates']) }} kategori khusus.</span>
                            @endif
                        </div>
                    @endisset
                </div>
                <div>
                    <small class="text-muted">Bergabung</small>
                    <div>{{ $shop->created_at->translatedFormat('d F Y') }}</div>
                </div>
            </div>
        </x-admin.card>
    </div>

    <div class="col-lg-8">
        <x-admin.card :padding="false" class="mb-4">
            <div class="card-header bg-transparent border-0 pt-3 px-3">
                <h6 class="fw-bold mb-0"><x-admin.icon name="box" :size="16" class="me-2" /> Produk Terbaru</h6>
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
                            <td><x-admin.badge :color="$product->status === 'approved' ? 'success' : 'warning'" :text="$product->status" /></td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center py-3 text-muted">Belum ada produk</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.card>

        <x-admin.card :padding="false">
            <div class="card-header bg-transparent border-0 pt-3 px-3">
                <h6 class="fw-bold mb-0"><x-admin.icon name="shopping-cart" :size="16" class="me-2" /> Pesanan Terbaru</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Pesanan</th><th>Total</th><th>Status</th><th>Tanggal</th></tr>
                    </thead>
                    <tbody>
                        @forelse($shop->orders as $order)
                        <tr>
                            <td class="fw-semibold">{{ $order->order_number }}</td>
                            <td>Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                            <td><x-admin.badge color="info" :text="$order->order_status" /></td>
                            <td class="small">{{ $order->created_at->format('d/m/Y') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center py-3 text-muted">Belum ada pesanan</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.card>
    </div>
</div>
@endsection
