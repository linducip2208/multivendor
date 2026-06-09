@extends('layouts.admin')

@section('title', 'Manajemen Kategori')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-list-alt me-2 text-success"></i> Kategori</h4>
        <p class="text-muted small mb-0">Kelola kategori produk marketplace</p>
    </div>
    <a href="{{ route('admin.categories.create') }}" class="btn btn-primary"><i class="fas fa-plus me-2"></i> Tambah Kategori</a>
</div>

<div class="card border-0 rounded-4 shadow-sm">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form method="GET" class="row g-2">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Cari kategori..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100"><i class="fas fa-search me-1"></i> Cari</button>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Kategori</th>
                        <th>Sub-Kategori</th>
                        <th>Status</th>
                        <th>Produk</th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $cat)
                    <tr>
                        <td class="ps-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-success-subtle text-success rounded-3 p-2">
                                    <i class="fas {{ $cat->icon ?? 'fa-folder' }}"></i>
                                </span>
                                <div>
                                    <div class="fw-semibold">{{ $cat->name }}</div>
                                    <small class="text-muted">{{ $cat->slug }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($cat->children->count() > 0)
                                @foreach($cat->children as $child)
                                    <span class="badge bg-light text-dark me-1">{{ $child->name }}</span>
                                @endforeach
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $cat->status ? 'success' : 'secondary' }}-subtle text-{{ $cat->status ? 'success' : 'secondary' }}">
                                {{ $cat->status ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td>{{ $cat->products_count ?? 0 }}</td>
                        <td class="text-end pe-3">
                            <a href="{{ route('admin.categories.edit', $cat) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                            <form action="{{ route('admin.categories.destroy', $cat) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus kategori ini?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-5 text-muted">Belum ada kategori</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($categories->hasPages())
        <div class="p-3 border-top">{{ $categories->links() }}</div>
        @endif
    </div>
</div>
@endsection
