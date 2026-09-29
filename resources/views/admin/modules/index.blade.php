@extends('layouts.admin')
@section('title', 'Modul')
@section('content')
<div class="mb-4"><h4 class="fw-bold"><x-admin.icon name="grid" :size="16" class="me-2" />Manajemen Modul</h4></div>
<x-admin.card :padding="false">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th class="text-uppercase small">MODUL</th><th class="text-uppercase small">DESKRIPSI</th><th class="text-uppercase small">VERSI</th><th class="text-uppercase small">STATUS</th><th></th></tr></thead>
            <tbody>
                @forelse($modules as $m)
                <tr><td><div class="fw-medium">{{ $m['name'] }}</div><small class="text-muted">{{ $m['alias'] }}</small></td><td>{{ $m['description'] }}</td><td>{{ $m['version'] }}</td><td><x-admin.badge :color="$m['active'] ? 'success' : 'secondary'" :text="$m['active'] ? 'Aktif' : 'Nonaktif'" /></td><td><form method="POST" action="{{ route('admin.modules.toggle') }}">@csrf <input type="hidden" name="module" value="{{ $m['alias'] }}"><button class="btn btn-sm btn-outline-{{ $m['active'] ? 'danger' : 'success' }}">{{ $m['active'] ? 'Nonaktifkan' : 'Aktifkan' }}</button></form></td></tr>
                @empty
                <tr><td colspan="5" class="text-center py-5 text-muted">Tidak ada modul terinstal.<br><small class="mt-2 d-block">Modul bisa ditambahkan di folder Modules/</small></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.card>
@endsection
