@extends('layouts.admin')
@section('title', 'Banner')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-image me-2 text-danger"></i> Banner</h4>
    <a href="{{ route('admin.banners.create') }}" class="btn btn-primary"><i class="fas fa-plus me-2"></i> Tambah Banner</a>
</div>
<div class="card border-0 rounded-4 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">
<thead class="table-light"><tr><th>Banner</th><th>Posisi</th><th>Link</th><th>Urutan</th><th>Status</th><th>Aksi</th></tr></thead>
<tbody>@forelse($banners as $b)
<tr><td><div class="fw-semibold">{{ $b->title }}</div><small class="text-muted">{{ Str::limit($b->subtitle, 50) }}</small></td><td><span class="badge bg-info-subtle text-info">{{ $b->position }}</span></td><td><small>{{ Str::limit($b->link, 30) }}</small></td><td>{{ $b->sort_order }}</td><td><span class="badge bg-{{ $b->status?'success'=>'secondary' }}-subtle">{{ $b->status?'Aktif'=>'Off' }}</span></td><td><a href="{{ route('admin.banners.edit', $b) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a><form action="{{ route('admin.banners.destroy', $b) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger ms-1"><i class="fas fa-trash"></i></button></form></td></tr>
@empty
<tr><td colspan="6" class="text-center py-5 text-muted">Belum ada banner</td></tr>
@endforelse
</tbody></table></div>
@if($banners->hasPages())<div class="p-3">{{ $banners->links() }}</div>@endif
</div>
@endsection
