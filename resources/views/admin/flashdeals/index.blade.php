@extends('layouts.admin')
@section('title', 'Flash Deal')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-bolt me-2 text-danger"></i> Flash Deal</h4>
    <a href="{{ route('admin.flashdeals.create') }}" class="btn btn-primary"><i class="fas fa-plus me-2"></i> Tambah Flash Deal</a>
</div>
<div class="card border-0 rounded-4 shadow-sm"><div class="table-responsive">
<table class="table table-hover mb-0">
    <thead class="table-light"><tr><th>Judul</th><th>Produk</th><th>Mulai</th><th>Berakhir</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
        @forelse($flashDeals as $fd)
        <tr>
            <td class="fw-semibold">{{ $fd->title }}</td>
            <td><span class="badge bg-info-subtle text-info">{{ $fd->products_count }} produk</span></td>
            <td class="small">{{ $fd->start_date->format('d/m/Y H:i') }}</td>
            <td class="small">{{ $fd->end_date->format('d/m/Y H:i') }}</td>
            <td><span class="badge bg-{{ $fd->status ? 'success' : 'secondary' }}-subtle">{{ $fd->status ? 'Aktif' : 'Off' }}</span> @if($fd->featured)<span class="badge bg-warning-subtle text-warning ms-1">Featured</span>@endif</td>
            <td>
                <a href="{{ route('admin.flashdeals.edit', $fd) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                <form action="{{ route('admin.flashdeals.destroy', $fd) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
            </td>
        </tr>
        @empty
        <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada flash deal</td></tr>
        @endforelse
    </tbody>
</table></div>
@if($flashDeals->hasPages())<div class="p-3">{{ $flashDeals->links() }}</div>@endif
</div>
@endsection
