@extends('layouts.admin')

@section('title', 'Moderasi Produk')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-box me-2 text-warning"></i> Moderasi Produk</h4>
        <p class="text-muted small mb-0">Review, approve, atau tolak produk dari semua vendor</p>
    </div>
</div>

<div class="card border-0 rounded-4 shadow-sm">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form method="GET" class="row g-2">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Cari produk atau SKU..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100"><i class="fas fa-search me-1"></i> Filter</button>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Produk</th>
                        <th>Toko</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th>Status</th>
                        <th>Tgl</th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                    <tr>
                        <td class="ps-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded bg-light d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                                    @if($product->thumbnail)
                                        <img src="{{ url('img/'.$product->thumbnail) }}" class="rounded" style="width:40px;height:40px;object-fit:contain;">
                                    @else
                                        <i class="fas fa-box text-muted"></i>
                                    @endif
                                </div>
                                <div>
                                    <div class="fw-semibold">{{ Str::limit($product->name, 40) }}</div>
                                    <small class="text-muted">{{ $product->sku ?? 'No SKU' }}</small>
                                </div>
                            </div>
                        </td>
                        <td><small>{{ $product->shop->name ?? '-' }}</small></td>
                        <td>Rp {{ number_format($product->price, 0, ',', '.') }}</td>
                        <td>{{ $product->current_stock }}</td>
                        <td>
                            @php $badges = ['pending' => 'warning', 'approved' => 'success', 'suspended' => 'danger']; @endphp
                            <span class="badge bg-{{ $badges[$product->status] ?? 'secondary' }}-subtle text-{{ $badges[$product->status] ?? 'secondary' }}">
                                {{ ucfirst($product->status) }}
                            </span>
                        </td>
                        <td class="small text-muted">{{ $product->created_at->format('d/m/Y') }}</td>
                        <td class="text-end pe-3">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="{{ route('admin.products.show', $product) }}"><i class="fas fa-eye me-2"></i> Detail</a></li>
                                    @if($product->status !== 'approved')
                                    <li>
                                        <form action="{{ route('admin.products.update-status', $product) }}" method="POST">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="status" value="approved">
                                            <button class="dropdown-item text-success"><i class="fas fa-check me-2"></i> Approve</button>
                                        </form>
                                    </li>
                                    @endif
                                    @if($product->status !== 'suspended')
                                    <li>
                                        <form action="{{ route('admin.products.update-status', $product) }}" method="POST">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="status" value="suspended">
                                            <button class="dropdown-item text-warning"><i class="fas fa-pause me-2"></i> Suspend</button>
                                        </form>
                                    </li>
                                    @endif
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="{{ route('admin.products.destroy', $product) }}" method="POST" onsubmit="return confirm('Hapus produk ini?')">
                                            @csrf @method('DELETE')
                                            <button class="dropdown-item text-danger"><i class="fas fa-trash me-2"></i> Hapus</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center py-5 text-muted">Belum ada produk</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($products->hasPages())
        <div class="p-3 border-top">{{ $products->links() }}</div>
        @endif
    </div>
</div>
@endsection
