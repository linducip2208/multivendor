@extends('layouts.admin')

@section('title', 'Kesehatan Sistem')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Sistem', ['label' => 'Kesehatan']]" />
@endsection

@section('content')
    <x-admin.page-header title="Kesehatan Sistem" subtitle="Ringkasan runtime, database, antrean, dan provider. Tidak ada rahasia yang ditampilkan.">
        <x-slot:actions>
            <form method="POST" action="{{ route('admin.maintenance.cache') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <x-admin.icon name="broom" :size="14" /> Bersihkan Cache
                </button>
            </form>
            <a href="{{ route('admin.system.queue') }}" class="btn btn-outline-secondary btn-sm">Antrean</a>
            <a href="{{ route('admin.system.logs') }}" class="btn btn-outline-secondary btn-sm">Log</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="PHP" :value="$report['php']['version']" icon="code" color="primary" :hint="'SAPI '.$report['php']['sapi']" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Laravel" :value="$report['laravel']['version']" icon="layers" color="info" :hint="'Lingkungan '.$report['laravel']['environment']" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat
                label="Latensi Database"
                :value="$report['database']['latency_ms'] === null ? 'Tidak dapat diukur' : $report['database']['latency_ms'].' ms'"
                icon="database"
                :color="$report['database']['latency_ms'] !== null && $report['database']['latency_ms'] < 50 ? 'success' : 'warning'"
            />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat
                label="Antrean Tertunda"
                :value="number_format($report['queue']['counts']['pending'], 0, ',', '.')"
                icon="list-ordered"
                :color="$report['queue']['counts']['pending'] > 0 ? 'warning' : 'success'"
                :hint="number_format($report['queue']['counts']['failed'], 0, ',', '.').' job gagal'"
            />
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <x-admin.card title="PHP" icon="code" class="mb-3">
                <dl class="row small mb-0">
                    <dt class="col-6 text-secondary">Versi</dt>
                    <dd class="col-6 text-end">{{ $report['php']['version'] }}</dd>
                    <dt class="col-6 text-secondary">Sistem operasi</dt>
                    <dd class="col-6 text-end">{{ strtoupper($report['php']['os']) }}</dd>
                    <dt class="col-6 text-secondary">Memory limit</dt>
                    <dd class="col-6 text-end">{{ $report['php']['memory_limit'] }}</dd>
                    <dt class="col-6 text-secondary">Max upload</dt>
                    <dd class="col-6 text-end">{{ $report['php']['upload_max'] }}</dd>
                    <dt class="col-6 text-secondary">Max post</dt>
                    <dd class="col-6 text-end">{{ $report['php']['post_max'] }}</dd>
                    <dt class="col-6 text-secondary">Waktu eksekusi</dt>
                    <dd class="col-6 text-end">{{ $report['php']['max_execution'] }}s</dd>
                    <dt class="col-6 text-secondary">OPcache</dt>
                    <dd class="col-6 text-end">{{ $report['php']['opcache'] }}</dd>
                </dl>
            </x-admin.card>

            <x-admin.card title="Ekstensi PHP" icon="check" :subtitle="$report['php']['extensions']['all_present'] ? 'Semua ekstensi wajib tersedia' : count($report['php']['extensions']['missing']).' ekstensi wajib hilang'">
                <div class="d-flex flex-wrap gap-1">
                    @foreach ($report['php']['extensions']['required'] as $extension)
                        <x-admin.badge
                            :text="$extension"
                            :color="extension_loaded($extension) ? 'success' : 'danger'"
                            pill
                        />
                    @endforeach
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-4">
            <x-admin.card title="Database" icon="database" class="mb-3">
                <dl class="row small mb-0">
                    <dt class="col-6 text-secondary">Koneksi</dt>
                    <dd class="col-6 text-end">{{ $report['database']['connection'] }}</dd>
                    <dt class="col-6 text-secondary">Driver</dt>
                    <dd class="col-6 text-end">{{ $report['database']['driver'] }}</dd>
                    <dt class="col-6 text-secondary">Versi</dt>
                    <dd class="col-6 text-end">{{ $report['database']['version'] ?? 'tidak diketahui' }}</dd>
                    <dt class="col-6 text-secondary">Ukuran</dt>
                    <dd class="col-6 text-end">{{ $report['database']['size_mb'] === null ? 'tidak diketahui' : number_format($report['database']['size_mb'], 2, ',', '.').' MB' }}</dd>
                    <dt class="col-6 text-secondary">Jumlah tabel</dt>
                    <dd class="col-6 text-end">{{ $report['database']['tables'] === null ? 'tidak diketahui' : number_format($report['database']['tables'], 0, ',', '.') }}</dd>
                    <dt class="col-6 text-secondary">Latensi</dt>
                    <dd class="col-6 text-end">{{ $report['database']['latency_ms'] === null ? 'tidak diukur' : $report['database']['latency_ms'].' ms' }}</dd>
                </dl>
            </x-admin.card>

            <x-admin.card title="Cache dan Antrean" icon="layers" class="mb-3">
                <dl class="row small mb-0">
                    <dt class="col-6 text-secondary">Driver cache</dt>
                    <dd class="col-6 text-end">
                        {{ $report['cache']['driver'] }}
                        <x-admin.badge :text="$report['cache']['writable'] ? 'OK' : 'Gagal'" :color="$report['cache']['writable'] ? 'success' : 'danger'" pill />
                    </dd>
                    <dt class="col-6 text-secondary">Latensi cache</dt>
                    <dd class="col-6 text-end">{{ $report['cache']['latency_ms'] === null ? 'tidak diukur' : $report['cache']['latency_ms'].' ms' }}</dd>
                    <dt class="col-6 text-secondary">Driver antrean</dt>
                    <dd class="col-6 text-end">{{ $report['queue']['driver'] }}</dd>
                    <dt class="col-6 text-secondary">Menunggu</dt>
                    <dd class="col-6 text-end">{{ number_format($report['queue']['counts']['pending'], 0, ',', '.') }}</dd>
                    <dt class="col-6 text-secondary">Dipesan</dt>
                    <dd class="col-6 text-end">{{ number_format($report['queue']['counts']['reserved'], 0, ',', '.') }}</dd>
                    <dt class="col-6 text-secondary">Gagal</dt>
                    <dd class="col-6 text-end">{{ number_format($report['queue']['counts']['failed'], 0, ',', '.') }}</dd>
                </dl>
            </x-admin.card>

            <x-admin.card title="Scheduler" icon="clock">
                <dl class="row small mb-0">
                    <dt class="col-6 text-secondary">Terakhir jalan</dt>
                    <dd class="col-6 text-end">{{ $report['scheduler']['last_run'] ?? 'belum pernah' }}</dd>
                    <dt class="col-6 text-secondary">Heartbeat</dt>
                    <dd class="col-6 text-end">{{ $report['scheduler']['heartbeat'] ?? 'belum ada' }}</dd>
                    <dt class="col-6 text-secondary">Kedaluwarsa</dt>
                    <dd class="col-6 text-end">
                        <x-admin.badge :text="$report['scheduler']['stale'] ? 'Ya' : 'Tidak'" :color="$report['scheduler']['stale'] ? 'warning' : 'success'" pill />
                    </dd>
                </dl>
            </x-admin.card>
        </div>

        <div class="col-lg-4">
            <x-admin.card title="Penyimpanan" icon="save" class="mb-3">
                <x-admin.alert
                    :type="$writable && $report['storage']['writable'] ? 'success' : 'danger'"
                    :title="$writable ? 'Uji tulis storage berhasil' : 'Uji tulis storage gagal'"
                />
                <dl class="row small mb-0">
                    <dt class="col-6 text-secondary">Disk</dt>
                    <dd class="col-6 text-end">{{ $report['storage']['disk'] }}</dd>
                    <dt class="col-6 text-secondary">Ukuran terpakai</dt>
                    <dd class="col-6 text-end">{{ number_format($report['storage']['bytes'] / 1048576, 1, ',', '.') }} MB</dd>
                    <dt class="col-6 text-secondary">Log</dt>
                    <dd class="col-6 text-end">{{ number_format($report['storage']['logs']['bytes'] / 1048576, 2, ',', '.') }} MB / {{ $report['storage']['logs']['count'] }} berkas</dd>
                </dl>
                <ul class="list-group list-group-flush mt-3">
                    @foreach ($report['storage']['directories'] as $directory)
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <code class="small">{{ $directory['path'] }}</code>
                            <div class="text-end">
                                <x-admin.badge :text="$directory['writable'] ? 'Writable' : 'Read-only'" :color="$directory['writable'] ? 'success' : 'danger'" pill />
                                <small class="d-block text-secondary">{{ number_format($directory['bytes'] / 1048576, 1, ',', '.') }} MB</small>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-admin.card>

            <x-admin.card title="Surat" icon="mail" subtitle="Hanya keberadaan kredensial yang ditampilkan, bukan nilainya.">
                <dl class="row small mb-0">
                    <dt class="col-6 text-secondary">Mailer</dt>
                    <dd class="col-6 text-end">{{ $report['mail']['mailer'] }}</dd>
                    <dt class="col-6 text-secondary">Dari</dt>
                    <dd class="col-6 text-end">{{ $report['mail']['from'] }}</dd>
                    <dt class="col-6 text-secondary">Host</dt>
                    <dd class="col-6 text-end">{{ $report['mail']['host'] !== '' ? $report['mail']['host'] : 'tidak diatur' }}</dd>
                    <dt class="col-6 text-secondary">Port</dt>
                    <dd class="col-6 text-end">{{ $report['mail']['port'] !== '' ? $report['mail']['port'] : '-' }}</dd>
                    <dt class="col-6 text-secondary">Sandi</dt>
                    <dd class="col-6 text-end">
                        <x-admin.badge :text="$report['mail']['credentials_present'] ? 'Ada' : 'Belum'" :color="$report['mail']['credentials_present'] ? 'success' : 'secondary'" pill />
                    </dd>
                </dl>
            </x-admin.card>
        </div>
    </div>

    <x-admin.card class="mb-3" title="Pemeriksaan Provider" icon="plug" subtitle="Status jangkauan. Kredensial tidak pernah ditampilkan." flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Provider</th>
                        <th scope="col">Tipe</th>
                        <th scope="col">Format</th>
                        <th scope="col">Host</th>
                        <th scope="col" class="text-center">Kredensial</th>
                        <th scope="col" class="text-center">Kesehatan</th>
                        <th scope="col">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['providers'] as $provider)
                        <tr>
                            <td>{{ $provider['name'] }}</td>
                            <td>{{ \Illuminate\Support\Str::headline($provider['type']) }}</td>
                            <td><code class="small">{{ $provider['api_format'] }}</code></td>
                            <td>{{ $provider['base_url_host'] }}</td>
                            <td class="text-center">
                                <x-admin.badge :text="$provider['has_credentials'] ? 'Ada' : 'Belum'" :color="$provider['has_credentials'] ? 'success' : 'secondary'" pill />
                            </td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="strtoupper($provider['health']['status'])"
                                    :color="match($provider['health']['status']) { 'healthy' => 'success', 'degraded' => 'warning', 'down', 'unreachable' => 'danger', default => 'secondary' }"
                                    pill
                                />
                            </td>
                            <td class="small text-secondary">{{ $provider['health']['detail'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-admin.empty-state compact icon="plug" title="Belum ada provider" text="Tambahkan provider di menu Integrasi." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <div class="row g-3">
        <div class="col-lg-7">
            <x-admin.card title="Kesalahan Terbaru" icon="alert-triangle" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col" class="text-center">Level</th>
                                <th scope="col">Pesan</th>
                                <th scope="col">Berkas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['errors'] as $error)
                                <tr>
                                    <td class="text-center">
                                        <x-admin.badge
                                            :text="$error['level']"
                                            :color="in_array($error['level'], ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'], true) ? 'danger' : 'warning'"
                                            pill
                                        />
                                    </td>
                                    <td class="small">{{ $error['message'] }}</td>
                                    <td><code class="small">{{ $error['file'] }}</code></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        <x-admin.empty-state compact icon="check" title="Tidak ada kesalahan" text="Log tidak memuat entri error terbaru." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-5">
            <x-admin.card title="Status Migrasi" icon="database" class="mb-3">
                @if ($report['migrations']['pending_count'] === 0)
                    <x-admin.alert type="success" :dismissible="false" title="Semua migrasi sudah dijalankan" />
                @else
                    <x-admin.alert type="warning" :dismissible="false" :title="$report['migrations']['pending_count'].' migrasi belum dijalankan'" />
                    <ul class="list-group list-group-flush small">
                        @foreach (array_slice($report['migrations']['pending'], 0, 10) as $migration)
                            <li class="list-group-item px-0"><code>{{ $migration }}</code></li>
                        @endforeach
                    </ul>
                @endif
                <p class="small text-secondary mb-0 mt-2">{{ number_format($report['migrations']['ran'], 0, ',', '.') }} migrasi telah dijalankan.</p>
            </x-admin.card>

            <x-admin.card title="Mode Maintenance" icon="tools">
                <div class="d-flex align-items-center justify-content-between">
                    <x-admin.badge
                        :text="$report['maintenance']['enabled'] ? 'Aktif' : 'Nonaktif'"
                        :color="$report['maintenance']['enabled'] ? 'danger' : 'success'"
                        pill
                    />
                    <a href="{{ route('admin.maintenance.index') }}" class="btn btn-sm btn-outline-secondary">Kelola</a>
                </div>
            </x-admin.card>
        </div>
    </div>
@endsection
