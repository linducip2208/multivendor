@extends('layouts.admin')
@section('title', 'Modules')
@section('content')
<div class="mb-4"><h4 class="fw-bold"><i class="fas fa-puzzle-piece me-2"></i>Module Management</h4></div>
<div class="card border-0 rounded-4 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th class="text-uppercase small">MODULE</th><th class="text-uppercase small">DESKRIPSI</th><th class="text-uppercase small">VERSI</th><th class="text-uppercase small">STATUS</th><th></th></tr></thead>
            <tbody>
                @forelse($modules as $m)
                <tr><td><div class="fw-medium">{{ $m['name'] }}</div><small class="text-muted">{{ $m['alias'] }}</small></td><td>{{ $m['description'] }}</td><td>{{ $m['version'] }}</td><td><span class="badge bg-{{ $m['active'] ? 'success' : 'secondary' }}-subtle text-{{ $m['active'] ? 'success' : 'secondary' }}">{{ $m['active'] ? 'Aktif' : 'Nonaktif' }}</span></td><td><form method="POST" action="{{ route('admin.modules.toggle') }}">@csrf <input type="hidden" name="module" value="{{ $m['alias'] }}"><button class="btn btn-sm btn-outline-{{ $m['active'] ? 'danger' : 'success' }}">{{ $m['active'] ? 'Nonaktifkan' : 'Aktifkan' }}</button></form></td></tr>
                @empty
                <tr><td colspan="5" class="text-center py-5 text-muted">Tidak ada module terinstall.<br><small class="mt-2 d-block">Module bisa ditambahkan di folder Modules/</small></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
