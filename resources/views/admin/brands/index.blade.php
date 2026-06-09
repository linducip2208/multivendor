@extends('layouts.admin')
@section('title', 'Brand')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-tag me-2 text-info"></i> Brand</h4>
    <a href="{{ route('admin.brands.create') }}" class="btn btn-primary"><i class="fas fa-plus me-2"></i> Tambah Brand</a>
</div>
<div class="card border-0 rounded-4 shadow-sm"><div class="p-3 border-bottom"><form method="GET"><input type="text" name="search" class="form-control" placeholder="Cari brand..." value="{{ request('search') }}"></form></div>
<div class="table-responsive"><table class="table table-hover mb-0">
<thead class="table-light"><tr><th>Nama</th><th>Slug</th><th>Deskripsi</th><th>Status</th><th>Aksi</th></tr></thead>
<tbody>
@forelse($brands as $b)
<tr><td class="fw-semibold">{{ $b->name }}</td><td><small>{{ $b->slug }}</small></td><td><small>{{ Str::limit($b->description, 50) }}</small></td>
<td><span class="badge bg-{{ $b->status ? 'success' : 'secondary' }}-subtle">{{ $b->status ? 'Aktif' : 'Nonaktif' }}</span></td>
<td>
<a href="{{ route('admin.brands.edit', $b) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
<form action="{{ route('admin.brands.destroy', $b) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
</td></tr>
@empty
<tr><td colspan="5" class="text-center py-5 text-muted">Belum ada brand</td></tr>
@endforelse
</tbody></table></div>
@if($brands->hasPages())<div class="p-3">{{ $brands->links() }}</div>@endif
</div>
@endsection
