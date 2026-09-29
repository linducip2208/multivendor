@extends('layouts.admin')

@section('title', 'Antrean')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['System', ['label' => 'Antrean']]" />
@endsection

@section('content')
    <x-admin.page-header title="Antrean dan Job" subtitle="Status antrean pekerjaan, scheduler, dan job yang gagal.">
        <x-slot:actions>
            <a href="{{ route('admin.system.health') }}" class="btn btn-outline-secondary btn-sm">Kesehatan Sistem</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat
                label="Menunggu"
                :value="number_format($queue['counts']['pending'] ?? 0, 0, ',', '.')"
                icon="clock"
                :color="($queue['counts']['pending'] ?? 0) > 0 ? 'warning' : 'success'"
            />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Dipesan" :value="number_format($queue['counts']['reserved'] ?? 0, 0, ',', '.')" icon="user-check" color="info" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Terjadwal" :value="number_format($queue['counts']['delayed'] ?? 0, 0, ',', '.')" icon="clock" color="secondary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat
                label="Gagal"
                :value="number_format($queue['counts']['failed'] ?? 0, 0, ',', '.')"
                icon="x-circle"
                :color="($queue['counts']['failed'] ?? 0) > 0 ? 'danger' : 'success'"
            />
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <x-admin.card title="Konfigurasi Antrean" icon="layers">
                <dl class="row small mb-0">
                    <dt class="col-6 text-secondary">Driver</dt>
                    <dd class="col-6 text-end">{{ $queue['driver'] }}</dd>
                    <dt class="col-6 text-secondary">Koneksi</dt>
                    <dd class="col-6 text-end">{{ $queue['connection'] }}</dd>
                    <dt class="col-6 text-secondary">Percobaan maksimum</dt>
                    <dd class="col-6 text-end">{{ $queue['max_tries'] ?? 'tidak diatur' }}</dd>
                </dl>
                @if (($queue['driver'] ?? '') === 'sync')
                    <x-admin.alert type="warning" :dismissible="false" title="Antrean berjalan sinkron" class="mt-3">
                        Dengan driver <code>sync</code> job dijalankan langsung di dalam request. Ini benar untuk pengembangan, tetapi akan memperlambat halaman yang menjadi antrean.
                    </x-admin.alert>
                @endif
            </x-admin.card>
        </div>
        <div class="col-lg-6">
            <x-admin.card title="Scheduler" icon="clock">
                <dl class="row small mb-0">
                    <dt class="col-6 text-secondary">Terakhir jalan</dt>
                    <dd class="col-6 text-end">{{ $scheduler['last_run'] ?? 'belum pernah' }}</dd>
                    <dt class="col-6 text-secondary">Heartbeat</dt>
                    <dd class="col-6 text-end">{{ $scheduler['heartbeat'] ?? 'belum ada' }}</dd>
                    <dt class="col-6 text-secondary">Batas kedaluwarsa</dt>
                    <dd class="col-6 text-end">{{ $scheduler['due_minutes'] }} menit</dd>
                </dl>
                <div class="mt-3">
                    <x-admin.badge
                        :text="$scheduler['stale'] ? 'Scheduler kemungkinan berhenti' : 'Scheduler sehat'"
                        :color="$scheduler['stale'] ? 'warning' : 'success'"
                        pill
                    />
                </div>
            </x-admin.card>
        </div>
    </div>

    <x-admin.card title="Job Gagal" icon="x-circle" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Waktu</th>
                        <th scope="col">Antrean</th>
                        <th scope="col">Koneksi</th>
                        <th scope="col">Kesalahan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($failed_jobs as $job)
                        <tr>
                            <td class="text-nowrap">{{ $job['failed_at'] }}</td>
                            <td>{{ $job['queue'] !== '' ? $job['queue'] : 'default' }}</td>
                            <td>{{ $job['connection'] }}</td>
                            <td class="small">{{ $job['error'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-admin.empty-state icon="check" title="Tidak ada job gagal" text="Semua job yang pernah dijalankan berhasil." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>
@endsection
