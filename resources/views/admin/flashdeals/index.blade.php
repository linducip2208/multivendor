@extends('layouts.admin')
@section('title', 'Flash Deal')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><x-admin.icon name="zap" :size="16" class="me-2 text-danger" /> Flash Deal</h4>
    <a href="{{ route('admin.flashdeals.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" class="me-2" /> Tambah Flash Deal</a>
</div>
<x-admin.card :padding="false"><div class="table-responsive">
<table class="table table-hover mb-0">
    <thead class="table-light"><tr><th>Judul</th><th>Produk</th><th>Mulai</th><th>Berakhir</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
        @forelse($flashDeals as $fd)
        <tr>
            <td class="fw-semibold">{{ $fd->title }}</td>
            <td><x-admin.badge color="info">{{ $fd->products_count }} produk</x-admin.badge></td>
            <td class="small">{{ $fd->start_date->format('d/m/Y H:i') }}</td>
            <td class="small">{{ $fd->end_date->format('d/m/Y H:i') }}</td>
            <td><x-admin.badge :color="$fd->status ? 'success' : 'secondary'" :text="$fd->status ? 'Aktif' : 'Off'" /> @if($fd->featured)<x-admin.badge color="warning" class="ms-1">Featured</x-admin.badge>@endif</td>
            <td>
                <a href="{{ route('admin.flashdeals.edit', $fd) }}" class="btn btn-sm btn-outline-primary"><x-admin.icon name="edit" :size="16" /></a>
                <form action="{{ route('admin.flashdeals.destroy', $fd) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><x-admin.icon name="trash" :size="16" /></button></form>
            </td>
        </tr>
        @empty
        <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada flash deal</td></tr>
        @endforelse
    </tbody>
</table></div>
@if($flashDeals->hasPages())<div class="p-3"><x-admin.pagination :paginator="$flashDeals" /></div>@endif
</x-admin.card>
@endsection
