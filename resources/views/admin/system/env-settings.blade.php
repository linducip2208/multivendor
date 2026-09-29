@extends('layouts.admin')

@section('title', 'Environment Settings')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['System', ['label' => 'Environment']]" />
@endsection

@section('content')
    <x-admin.page-header title="Konfigurasi Environment" subtitle="Hanya kunci yang ada dalam daftar izin yang dapat ditulis." />

    <x-admin.alert type="danger" :title="'Operasi berisiko tinggi'" icon="alert-triangle">
        Menulis berkas <code>.env</code> memerlukan hak super admin. Setiap penyimpanan membuat cadangan di <code>storage/app/backups/env</code> dan membersihkan cache konfigurasi.
    </x-admin.alert>

    <x-admin.card title="Status Berkas" icon="file-code" class="mb-3">
        <dl class="row small mb-0">
            <dt class="col-4 text-secondary">Lokasi</dt>
            <dd class="col-8 text-end text-break"><code>{{ $env_path }}</code></dd>
            <dt class="col-4 text-secondary">Status</dt>
            <dd class="col-8 text-end">
                <x-admin.badge :text="$env_exists ? 'Ada' : 'Belum ada'" :color="$env_exists ? 'success' : 'warning'" pill />
            </dd>
        </dl>
    </x-admin.card>

    <form method="POST" action="{{ route('admin.system.env-settings-update') }}">
        @csrf
        @method('PUT')
        <x-admin.card title="Kunci yang Diizinkan" icon="settings" flush>
            <div class="table-responsive">
                <table class="table admin-table mb-0 table-hover">
                    <thead>
                        <tr>
                            <th scope="col">Kunci</th>
                            <th scope="col">Nilai</th>
                            <th scope="col" class="text-center">Tersimpan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($settings as $setting)
                            <tr>
                                <td><code class="small">{{ $setting['key'] }}</code></td>
                                <td>
                                    @if ($setting['masked'])
                                        <input
                                            type="password"
                                            class="form-control form-control-sm"
                                            name="settings[{{ $setting['key'] }}]"
                                            value=""
                                            maxlength="2048"
                                            autocomplete="off"
                                            aria-label="Nilai {{ $setting['key'] }}"
                                        >
                                        <small class="text-secondary">Nilai lama dipertahankan bila kolom dibiarkan kosong.</small>
                                    @else
                                        <input
                                            type="text"
                                            class="form-control form-control-sm"
                                            name="settings[{{ $setting['key'] }}]"
                                            value="{{ $setting['value'] }}"
                                            maxlength="2048"
                                            aria-label="Nilai {{ $setting['key'] }}"
                                        >
                                    @endif
                                </td>
                                <td class="text-center">
                                    <x-admin.badge :text="$setting['configured'] ? 'Ya' : 'Tidak'" :color="$setting['configured'] ? 'success' : 'secondary'" pill />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                    <x-admin.icon name="save" :size="14" /> Simpan Environment
                </button>
            </div>
        </x-admin.card>
    </form>
@endsection
