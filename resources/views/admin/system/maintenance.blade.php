@extends('layouts.admin')

@section('title', 'Maintenance')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['System', ['label' => 'Maintenance']]" />
@endsection

@section('content')
    <x-admin.page-header title="Maintenance" subtitle="Mode pemeliharaan dan pembersihan cache." />

    <div class="row g-3">
        <div class="col-lg-6">
            <x-admin.card title="Mode Maintenance" icon="power" class="mb-3">
                <x-admin.alert
                    :type="$down ? 'danger' : 'success'"
                    :title="$down ? 'Mode maintenance sedang aktif' : 'Situs dapat diakses publik'"
                />
                <form method="POST" action="{{ route('admin.maintenance.toggle') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <x-admin.form-field
                                name="message"
                                label="Pesan untuk Pengunjung"
                                type="textarea"
                                :rows="2"
                                :maxlength="1000"
                                value="Situs sedang dalam pemeliharaan."
                            />
                        </div>
                        <div class="col-12">
                            <x-admin.form-field
                                name="secret"
                                label="Kode Akses Bypass"
                                type="text"
                                :maxlength="60"
                                help="Kosongkan untuk menonaktifkan akses bypass."
                            />
                        </div>
                    </div>
                    <button type="submit" class="btn w-100 mt-3 btn-{{ $down ? 'success' : 'danger' }}">
                        {{ $down ? 'Nonaktifkan Maintenance' : 'Aktifkan Maintenance' }}
                    </button>
                </form>
            </x-admin.card>
        </div>

        <div class="col-lg-6">
            <x-admin.card title="Cache" icon="broom" subtitle="Terakhir dibersihkan {{ $cache['at'] }}." class="mb-3">
                <p class="small text-secondary">
                    Membersihkan cache membuat perubahan pada pengaturan dan tema langsung terlihat. Yang dibersihkan: {{ implode(', ', $cache['cleared']) }}.
                </p>
                <form method="POST" action="{{ route('admin.maintenance.cache') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-warning w-100">Bersihkan Seluruh Cache</button>
                </form>
            </x-admin.card>

            <x-admin.card title="Lingkungan" icon="code">
                <dl class="row small mb-0">
                    <dt class="col-6 text-secondary">APP_ENV</dt>
                    <dd class="col-6 text-end"><code>{{ $env }}</code></dd>
                    <dt class="col-6 text-secondary">Status</dt>
                    <dd class="col-6 text-end">
                        <x-admin.badge :text="$down ? 'Maintenance' : 'Normal'" :color="$down ? 'danger' : 'success'" pill />
                    </dd>
                </dl>
            </x-admin.card>
        </div>
    </div>
@endsection
