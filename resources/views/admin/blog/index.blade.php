@extends('layouts.admin')
@section('title', 'Blog')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><x-admin.icon name="file-text" :size="16" class="me-2 text-purple" /> Blog</h4>
    <a href="{{ route('admin.blog.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" class="me-2" /> Tulis Artikel</a>
</div>
<x-admin.card :padding="false"><div class="table-responsive">
<table class="table table-hover mb-0">
    <thead class="table-light"><tr><th>Judul</th><th>Penulis</th><th>Status</th><th>Tgl Terbit</th><th>Aksi</th></tr></thead>
    <tbody>
        @forelse($posts as $p)
        <tr>
            <td class="fw-semibold">{{ Str::limit($p->title, 60) }}</td>
            <td><small>{{ $p->author->name ?? '-' }}</small></td>
            <td><x-admin.badge :color="$p->is_published ? 'success' : 'secondary'" :text="$p->is_published ? 'Terbit' : 'Draf'" /></td>
            <td class="small">{{ $p->published_at?->format('d/m/Y') ?? '-' }}</td>
            <td>
                <a href="{{ route('admin.blog.edit', $p) }}" class="btn btn-sm btn-outline-primary"><x-admin.icon name="edit" :size="16" /></a>
                <form action="{{ route('admin.blog.destroy', $p) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><x-admin.icon name="trash" :size="16" /></button></form>
            </td>
        </tr>
        @empty
        <tr><td colspan="5" class="text-center py-5 text-muted">Belum ada artikel</td></tr>
        @endforelse
    </tbody>
</table></div>
@if($posts->hasPages())<div class="p-3"><x-admin.pagination :paginator="$posts" /></div>@endif
</x-admin.card>
@endsection
