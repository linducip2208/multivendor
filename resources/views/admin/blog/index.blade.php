@extends('layouts.admin')
@section('title', 'Blog')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-blog me-2 text-purple"></i> Blog</h4>
    <a href="{{ route('admin.blog.create') }}" class="btn btn-primary"><i class="fas fa-plus me-2"></i> Tulis Artikel</a>
</div>
<div class="card border-0 rounded-4 shadow-sm"><div class="table-responsive">
<table class="table table-hover mb-0">
    <thead class="table-light"><tr><th>Judul</th><th>Penulis</th><th>Status</th><th>Tgl Publish</th><th>Aksi</th></tr></thead>
    <tbody>
        @forelse($posts as $p)
        <tr>
            <td class="fw-semibold">{{ Str::limit($p->title, 60) }}</td>
            <td><small>{{ $p->author->name ?? '-' }}</small></td>
            <td><span class="badge bg-{{ $p->is_published ? 'success' : 'secondary' }}-subtle">{{ $p->is_published ? 'Published' : 'Draft' }}</span></td>
            <td class="small">{{ $p->published_at?->format('d/m/Y') ?? '-' }}</td>
            <td>
                <a href="{{ route('admin.blog.edit', $p) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                <form action="{{ route('admin.blog.destroy', $p) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
            </td>
        </tr>
        @empty
        <tr><td colspan="5" class="text-center py-5 text-muted">Belum ada artikel</td></tr>
        @endforelse
    </tbody>
</table></div>
@if($posts->hasPages())<div class="p-3">{{ $posts->links() }}</div>@endif
</div>
@endsection
