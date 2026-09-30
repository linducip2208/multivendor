@extends('layouts.admin')
@section('title', 'Blog')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><x-admin.icon name="file-text" :size="16" class="me-2 text-purple" /> Blog</h4>
    <a href="{{ route('admin.blog.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" class="me-2" /> Tulis Artikel</a>
</div>
{{-- Tab locale ID/EN (tampilan saja; status per bahasa draft/published, fallback ID) --}}
<ul class="nav nav-tabs mb-3" data-locale-tabs role="tablist">
    <li class="nav-item" role="presentation"><button type="button" class="nav-link active" data-locale-tab="id" role="tab">ID</button></li>
    <li class="nav-item" role="presentation"><button type="button" class="nav-link" data-locale-tab="en" role="tab">EN</button></li>
    <li class="nav-item ms-auto d-flex align-items-center"><span class="text-muted small">Fallback: ID</span></li>
</ul>
<x-admin.card class="mb-3">
    <form method="GET" action="{{ route('admin.blog.index') }}" class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label" for="blog-search">Cari judul</label>
            <input id="blog-search" type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Ketik judul artikel…">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="blog-status">Status</label>
            <select id="blog-status" name="status" class="form-select">
                <option value="">Semua status</option>
                <option value="draft" @selected(request('status') === 'draft')>Draf</option>
                <option value="scheduled" @selected(request('status') === 'scheduled')>Terjadwal</option>
                <option value="published" @selected(request('status') === 'published')>Terbit</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Terapkan</button>
            @if (request('search') || request('status'))
                <a href="{{ route('admin.blog.index') }}" class="btn btn-outline-secondary">Atur ulang</a>
            @endif
        </div>
    </form>
    <p class="small text-secondary mb-0 mt-2">Artikel <strong>Terjadwal</strong> terbit otomatis saat waktunya tiba — storefront hanya menampilkan yang <code>published_at &lt;= sekarang</code>, tanpa command tambahan. Workflow per bahasa (draft/review/published/scheduled) via <code>BlogController::updateLocale / updateWorkflow</code> + <code>ContentWorkflowService</code> — perlu wiring route oleh integrator; terjemahan overlay di <code>blog_post_translations</code> existing.</p>
</x-admin.card>
<x-admin.card :padding="false"><div class="table-responsive">
<table class="table table-hover mb-0">
    <thead class="table-light"><tr><th>Judul</th><th>Penulis</th><th>Status</th><th>Tgl Terbit</th><th>Aksi</th></tr></thead>
    <tbody>
        @forelse($posts as $p)
        @php $st = $p->schedule_status ?? \App\Http\Controllers\Admin\BlogController::scheduleStatus($p); @endphp
        <tr>
            <td class="fw-semibold">{{ Str::limit($p->title, 60) }}</td>
            <td><small>{{ $p->author->name ?? '-' }}</small></td>
            <td><x-admin.badge :color="$st['color']" :text="$st['label']" /></td>
            <td class="small">{{ $p->published_at?->format('d/m/Y H:i') ?? '-' }}</td>
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
