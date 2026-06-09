@extends('layouts.vendor')

@section('title', 'Produk Saya')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-box me-2 text-success"></i> Produk Saya</h4>
        <p class="text-muted small mb-0">Kelola produk di toko {{ $shop->name }}</p>
    </div>
    <a href="{{ route('vendor.products.create') }}" class="btn btn-success"><i class="fas fa-plus me-2"></i> Tambah Produk</a>
</div>

<div class="card border-0 rounded-4 shadow-sm">
    <div class="p-3 border-bottom">
        <form method="GET" class="row g-2">
            <div class="col-md-3"><input type="text" name="search" class="form-control" placeholder="Cari produk..." value="{{ request('search') }}"></div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">Semua</option>
                    <option value="pending" {{ request('status')==='pending'? '' : '' }}>Pending</option>
                    <option value="approved" {{ request('status')==='approved'? '' : '' }}>Approved</option>
                    <option value="suspended" {{ request('status')==='suspended'? '' : '' }}>Suspended</option>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-outline-success w-100"><i class="fas fa-search me-1"></i>Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>Produk</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                @forelse($products as $p)
                <tr>
                    <td class="fw-medium">{{ Str::limit($p->name, 50) }}</td>
                    <td><small>{{ $p->category->name ?? '-' }}</small></td>
                    <td>Rp {{ number_format($p->price, 0, ',', '.') }}</td>
                    <td>{{ $p->current_stock }}</td>
                    <td>
                        @php $b = ['pending'=>'warning','approved'=>'success','suspended'=>'danger']; @endphp
                        <span class="badge bg-{{ $b[$p->status] }}-subtle text-{{ $b[$p->status] }}">{{ ucfirst($p->status) }}</span>
                    </td>
                    <td>
                        <a href="{{ route('vendor.products.edit', $p) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                        <form action="{{ route('vendor.products.destroy', $p) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada produk. <a href="{{ route('vendor.products.create') }}">Tambah produk pertama</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($products->hasPages())<div class="p-3">{{ $products->links() }}</div>@endif
</div>
@endsection
