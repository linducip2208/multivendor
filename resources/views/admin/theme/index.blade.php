@extends('layouts.admin')
@section('title', 'Pengaturan Tema')
@section('content')
<div class="mb-4"><h4 class="fw-bold"><x-admin.icon name="palette" :size="16" class="me-2" />Pengaturan Tema</h4></div>
<x-admin.card :padding="false" style="max-width:600px">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.theme.update') }}">
            @csrf @method('PUT')
            <div class="mb-3"><label class="form-label fw-medium">Warna Primary</label>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    @foreach($colors as $c)<label style="width:36px;height:36px;border-radius:50%;background:{{ $c }};cursor:pointer;border:2px solid {{ \App\Models\SystemSetting::get('theme_primary_color','#4F46E5')==$c ? '#000' : 'transparent' }}"><input type="radio" name="theme_primary_color" value="{{ $c }}" {{ \App\Models\SystemSetting::get('theme_primary_color','#4F46E5')==$c ? 'checked' : '' }} style="display:none"></label>@endforeach
                </div>
                <input type="color" name="theme_primary_color" class="form-control form-control-color" value="{{ \App\Models\SystemSetting::get('theme_primary_color','#4F46E5') }}">
            </div>
            <div class="mb-3"><label class="form-label fw-medium">Warna Primary Dark</label><input type="color" name="theme_primary_dark" class="form-control form-control-color" value="{{ \App\Models\SystemSetting::get('theme_primary_dark','#3730A3') }}"></div>
            <div class="mb-3"><label class="form-label fw-medium">Border Radius (px)</label><input type="number" name="theme_border_radius" class="form-control" value="{{ \App\Models\SystemSetting::get('theme_border_radius','14') }}"></div>
            <div class="mb-3"><label class="form-label fw-medium">Font</label><select name="theme_font_family" class="form-select"><option value="Inter" {{ \App\Models\SystemSetting::get('theme_font_family','Inter')=='Inter'?'selected':'' }}>Inter</option><option value="Poppins">Poppins</option><option value="Plus Jakarta Sans">Plus Jakarta Sans</option></select></div>
            <div class="mb-3"><div class="form-check"><input type="checkbox" name="theme_dark_mode_default" class="form-check-input" value="1" {{ \App\Models\SystemSetting::get('theme_dark_mode_default') ? 'checked' : '' }}><label class="form-check-label">Dark mode default</label></div></div>
            <div class="mb-3"><div class="form-check"><input type="checkbox" name="theme_show_language_switcher" class="form-check-input" value="1" {{ \App\Models\SystemSetting::get('theme_show_language_switcher','1') ? 'checked' : '' }}><label class="form-check-label">Tampilkan language switcher</label></div></div>
            <div class="mb-3"><label class="form-label fw-medium">Logo Text</label><input type="text" name="theme_logo_text" class="form-control" value="{{ \App\Models\SystemSetting::get('theme_logo_text',config('app.name')) }}"></div>
            <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" class="me-2" />Simpan</button>
        </form>
    </div>
</x-admin.card>

<div class="mb-4 mt-4"><h4 class="fw-bold"><x-admin.icon name="layers" :size="16" class="me-2" />Tema Terpasang</h4>
<p class="text-muted small">Aktif: <strong>{{ $temaAktif }}</strong>. Pratinjau hanya untuk admin login via tautan khusus.</p></div>
<x-admin.card :padding="false">
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Kode</th><th>Nama</th><th>Versi</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
            @forelse ($daftarTema as $tema)
                <tr>
                    <td><code>{{ $tema['code'] }}</code></td>
                    <td class="fw-medium">{{ $tema['name'] }}</td>
                    <td>{{ $tema['version'] }}</td>
                    <td>@if ($tema['active'])<x-admin.badge text="Aktif" color="success" pill />@else<span class="text-muted">-</span>@endif</td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Aksi tema {{ $tema['code'] }}">
                            @if (! $tema['active'])
                                <form method="POST" action="{{ route('admin.theme.activate') }}" class="d-inline">@csrf<input type="hidden" name="theme" value="{{ $tema['code'] }}"><button type="submit" class="btn btn-outline-success">Aktifkan</button></form>
                            @endif
                            <form method="POST" action="{{ route('admin.theme.preview') }}" class="d-inline">@csrf<input type="hidden" name="theme" value="{{ $tema['code'] }}"><button type="submit" class="btn btn-outline-secondary">Pratinjau</button></form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada tema.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</x-admin.card>

<div class="row g-3 mt-1">
    <div class="col-md-4">
        <x-admin.card title="Duplikasi Tema" icon="copy">
            <form method="POST" action="{{ route('admin.theme.duplicate') }}">@csrf
                <div class="mb-2"><label class="form-label">Dari</label><input type="text" name="from" class="form-control" value="default" required maxlength="80"></div>
                <div class="mb-2"><label class="form-label">Kode Baru</label><input type="text" name="to" class="form-control" required maxlength="80" placeholder="mis. lebaran"></div>
                <button type="submit" class="btn btn-outline-primary btn-sm">Duplikasi</button>
            </form>
        </x-admin.card>
    </div>
    <div class="col-md-4">
        <x-admin.card title="Jadwalkan Aktivasi" icon="clock">
            <form method="POST" action="{{ route('admin.theme.schedule') }}">@csrf
                <div class="mb-2"><label class="form-label">Tema</label><input type="text" name="theme" class="form-control" required maxlength="80"></div>
                <div class="mb-2"><label class="form-label">Waktu</label><input type="datetime-local" name="at" class="form-control" required></div>
                <button type="submit" class="btn btn-outline-primary btn-sm">Jadwalkan</button>
            </form>
        </x-admin.card>
    </div>
    <div class="col-md-4">
        <x-admin.card title="Riwayat (Rollback)" icon="history">
            @if (isset($snapshots) && count($snapshots) > 0)
                <ul class="list-unstyled mb-0">
                    @foreach ($snapshots as $i => $snap)
                        <li class="d-flex justify-content-between align-items-center py-1 border-bottom">
                            <span class="small">{{ $snap['label'] ?? 'snapshot' }}<br><span class="text-muted">{{ $snap['at'] ?? '' }}</span></span>
                            <form method="POST" action="{{ route('admin.theme.rollback') }}">@csrf<input type="hidden" name="index" value="{{ $i }}"><button type="submit" class="btn btn-outline-warning btn-sm">Kembalikan</button></form>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-muted small mb-0">Belum ada snapshot. Snapshot dibuat otomatis saat aktivasi/rollback.</p>
            @endif
        </x-admin.card>
    </div>
</div>
@endsection
