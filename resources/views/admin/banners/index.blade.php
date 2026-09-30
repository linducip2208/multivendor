@extends('layouts.admin')
@section('title', 'Banner')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><x-admin.icon name="image" :size="16" class="me-2 text-danger" /> Banner</h4>
    <a href="{{ route('admin.banners.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" class="me-2" /> Tambah Banner</a>
</div>
@isset($segmentMap)
<x-admin.card class="mb-3" title="Personalisasi Segmen" icon="layers">
    <p class="small text-secondary mb-2">Banner tanpa segmen tampil untuk semua pengunjung. Banner tertarget hanya tampil untuk anggota segmennya.</p>
    <div class="d-flex flex-wrap gap-2">
        @forelse($segmentMap as $bannerId => $segmenIds)
            <x-admin.badge :text="'Banner #'.$bannerId.': '.count($segmenIds).' segmen'" color="info" pill />
        @empty
            <span class="small text-secondary">Belum ada banner tertarget — semua banner bersifat publik.</span>
        @endforelse
    </div>
</x-admin.card>
@endisset
<x-admin.card :padding="false"><div class="table-responsive"><table class="table table-hover mb-0">
<thead class="table-light"><tr><th>Banner</th><th>Posisi</th><th>Eksperimen</th><th>Tautan</th><th>Urutan</th><th>Status</th><th>Penjadwalan</th><th>Segmen</th><th>CTR</th><th>Aksi</th></tr></thead>
<tbody>@forelse($banners as $i => $b)
<tr><td><div class="fw-semibold">{{ $b->title }}</div><small class="text-muted">{{ Str::limit($b->subtitle ?? '', 50) }}</small></td><td><x-admin.badge color="info" :text="$b->position" /></td><td>@if(!empty($b->experiment_key ?? null))<x-admin.badge color="warning" :text="($b->experiment_key ?? '').' · bobot '.($b->weight ?? 100)" />@else<small class="text-muted">—</small>@endif</td><td><small>{{ Str::limit($b->link ?? '', 30) }}</small></td><td>{{ $b->sort_order }}</td><td><x-admin.badge :color="$b->status?'success' : 'secondary'" :text="$b->status?'Aktif' : 'Nonaktif'" /></td><td><small class="text-muted">{{ ($scheduled[$i]['detail'] ?? '—') }}</small> <x-admin.badge :color="$scheduled[$i]['badge'] ?? 'secondary'" :text="$scheduled[$i]['state'] ?? '—'" /></td><td>@isset($segmentMap)@if(!empty($segmentMap[$b->id] ?? []))<x-admin.badge :text="count($segmentMap[$b->id]).' segmen'" color="primary" pill />@else<small class="text-muted">Publik</small>@endif @else <small class="text-muted">—</small> @endisset</td><td><small class="text-muted">{{ number_format($b->impressions ?? 0, 0, ',', '.') }} tayang · {{ number_format($b->clicks ?? 0, 0, ',', '.') }} klik · <span class="fw-semibold">{{ ($b->impressions ?? 0) > 0 ? number_format((($b->clicks ?? 0) / max(1, $b->impressions)) * 100, 2, ',', '.').'%' : '—' }}</span></small></td><td><a href="{{ route('admin.banners.edit', $b) }}" class="btn btn-sm btn-outline-primary"><x-admin.icon name="edit" :size="16" /></a><form action="{{ route('admin.banners.destroy', $b) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger ms-1"><x-admin.icon name="trash" :size="16" /></button></form></td></tr>
@empty
<tr><td colspan="10" class="text-center py-5 text-muted">Belum ada banner</td></tr>
@endforelse
</tbody></table></div>
@if($banners->hasPages())<div class="p-3"><x-admin.pagination :paginator="$banners" /></div>@endif
</x-admin.card>
@endsection
