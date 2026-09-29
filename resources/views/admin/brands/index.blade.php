@extends('layouts.admin')
@section('title', 'Brand')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><x-admin.icon name="tag" :size="20" class="me-2 text-info" /> Brand</h4>
    <a href="{{ route('admin.brands.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" class="me-2" /> Tambah Brand</a>
</div>
<x-admin.card :padding="false"><div class="p-3 border-bottom"><form method="GET"><input type="text" name="search" class="form-control" placeholder="Cari brand..." value="{{ request('search') }}"></form></div>
<div class="table-responsive"><table class="table table-hover mb-0">
<thead class="table-light"><tr><th>Nama</th><th>Slug</th><th>Deskripsi</th><th>Status</th><th>Aksi</th></tr></thead>
<tbody>
@forelse($brands as $b)
<tr><td class="fw-semibold">{{ $b->name }}</td><td><small>{{ $b->slug }}</small></td><td><small>{{ Str::limit($b->description, 50) }}</small></td>
<td><x-admin.badge :color="$b->status ? 'success' : 'secondary'" :text="$b->status ? 'Aktif' : 'Nonaktif'" /></td>
<td>
<a href="{{ route('admin.brands.edit', $b) }}" class="btn btn-sm btn-outline-primary"><x-admin.icon name="pencil" :size="14" /></a>
<form action="{{ route('admin.brands.destroy', $b) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><x-admin.icon name="trash" :size="14" /></button></form>
</td></tr>
@empty
<tr><td colspan="5" class="text-center py-5 text-muted">Belum ada brand</td></tr>
@endforelse
</tbody></table></div>
@if($brands->hasPages())<div class="p-3"><x-admin.pagination>{{ $brands->links() }}</x-admin.pagination></div>@endif
</x-admin.card>
@endsection
