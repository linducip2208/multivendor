@extends('layouts.admin')
@section('title', 'Banner')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><x-admin.icon name="image" :size="16" class="me-2 text-danger" /> Banner</h4>
    <a href="{{ route('admin.banners.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" class="me-2" /> Tambah Banner</a>
</div>
<x-admin.card :padding="false"><div class="table-responsive"><table class="table table-hover mb-0">
<thead class="table-light"><tr><th>Banner</th><th>Posisi</th><th>Tautan</th><th>Urutan</th><th>Status</th><th>Aksi</th></tr></thead>
<tbody>@forelse($banners as $b)
<tr><td><div class="fw-semibold">{{ $b->title }}</div><small class="text-muted">{{ Str::limit($b->subtitle ?? '', 50) }}</small></td><td><x-admin.badge color="info" :text="$b->position" /></td><td><small>{{ Str::limit($b->link ?? '', 30) }}</small></td><td>{{ $b->sort_order }}</td><td><x-admin.badge :color="$b->status?'success' : 'secondary'" :text="$b->status?'Aktif' : 'Nonaktif'" /></td><td><a href="{{ route('admin.banners.edit', $b) }}" class="btn btn-sm btn-outline-primary"><x-admin.icon name="edit" :size="16" /></a><form action="{{ route('admin.banners.destroy', $b) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger ms-1"><x-admin.icon name="trash" :size="16" /></button></form></td></tr>
@empty
<tr><td colspan="6" class="text-center py-5 text-muted">Belum ada banner</td></tr>
@endforelse
</tbody></table></div>
@if($banners->hasPages())<div class="p-3"><x-admin.pagination :paginator="$banners" /></div>@endif
</x-admin.card>
@endsection
