@extends('layouts.admin')

@section('title', 'Beranda')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Pemasaran', ['label' => 'Beranda']]" />
@endsection

@section('content')
    <x-admin.page-header title="Komposisi Homepage" :subtitle="$enabled_count.' dari '.$total_count.' bagian aktif di storefront.'">
        <x-slot:actions>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="preview-homepage" data-preview-url="{{ route('admin.homepage.preview') }}">
                <x-admin.icon name="eye" :size="14" /> Pratinjau Data
            </button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.alert type="info" :dismissible="false" title="Urutan menentukan tampilan storefront" icon="info">
        Section tanpa baris tersimpan memakai urutan bawaan dari registry. Simpan untuk menulis urutan, judul, dan pengaturan ke <code>homepage_sections</code> dan menyegarkan cache homepage.
    </x-admin.alert>

    <form method="POST" action="{{ route('admin.homepage.update') }}">
        @csrf
        <x-admin.card title="Section" icon="layout" flush>
            <div class="table-responsive">
                <table class="table admin-table mb-0 table-hover" data-sortable-rows>
                    <thead>
                        <tr>
                            <th scope="col" style="width: 44px">#</th>
                            <th scope="col">Judul</th>
                            <th scope="col" style="width: 120px">Perangkat</th>
                            <th scope="col" style="width: 110px">Jumlah</th>
                            <th scope="col" class="text-center" style="width: 100px">Aktif</th>
                            <th scope="col">Subjudul</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $index => $row)
                            <tr data-sortable-row>
                                <td class="text-secondary" data-sortable-handle aria-label="Urutan {{ $index + 1 }}">{{ $index + 1 }}</td>
                                <td>
                                    <input type="hidden" name="sections[{{ $index }}][code]" value="{{ $row['code'] }}">
                                    <label class="form-label small mb-1" for="title-{{ $row['code'] }}">
                                        <x-admin.icon :name="$row['icon']" :size="14" class="me-1" />{{ $row['registry_title'] }}
                                    </label>
                                    <input
                                        type="text"
                                        class="form-control form-control-sm"
                                        id="title-{{ $row['code'] }}"
                                        name="sections[{{ $index }}][title]"
                                        value="{{ $row['title'] }}"
                                        maxlength="160"
                                    >
                                </td>
                                <td>
                                    <label class="visually-hidden" for="devices-{{ $row['code'] }}">Perangkat untuk {{ $row['registry_title'] }}</label>
                                    <select class="form-select form-select-sm" id="devices-{{ $row['code'] }}" name="sections[{{ $index }}][devices]">
                                        @foreach ($devices as $device)
                                            <option value="{{ $device }}" @selected($row['devices'] === $device)>
                                                {{ $device === 'all' ? 'Semua' : ucfirst($device) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <label class="visually-hidden" for="limit-{{ $row['code'] }}">Jumlah item untuk {{ $row['registry_title'] }}</label>
                                    <input
                                        type="number"
                                        class="form-control form-control-sm"
                                        id="limit-{{ $row['code'] }}"
                                        name="sections[{{ $index }}][limit]"
                                        value="{{ $row['limit'] }}"
                                        min="1"
                                        max="24"
                                    >
                                </td>
                                <td class="text-center">
                                    <input type="hidden" name="sections[{{ $index }}][is_enabled]" value="0">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        name="sections[{{ $index }}][is_enabled]"
                                        value="1"
                                        id="enabled-{{ $row['code'] }}"
                                        @checked($row['is_enabled'])
                                        aria-label="Aktifkan {{ $row['registry_title'] }}"
                                    >
                                </td>
                                <td>
                                    <label class="visually-hidden" for="subtitle-{{ $row['code'] }}">Subjudul untuk {{ $row['registry_title'] }}</label>
                                    <input
                                        type="text"
                                        class="form-control form-control-sm"
                                        id="subtitle-{{ $row['code'] }}"
                                        name="sections[{{ $index }}][subtitle]"
                                        value="{{ $row['subtitle'] }}"
                                        maxlength="255"
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary" data-reset-order>Reset Urutan</button>
                <button type="submit" class="btn btn-primary">
                    <x-admin.icon name="save" :size="14" /> Simpan Beranda
                </button>
            </div>
        </x-admin.card>
    </form>

    <x-admin.modal id="preview-modal" title="Pratinjau Data Beranda" icon="eye" size="lg">
        <div id="preview-body">
            <x-admin.skeleton type="text" :rows="4" />
        </div>
    </x-admin.modal>

    @isset($versions)
        @if (count($versions) > 0)
            <x-admin.card title="Riwayat versi" icon="history" class="mt-3">
                <ul class="list-unstyled mb-0 small">
                    @foreach ($versions as $version)
                        <li class="py-1 {{ $loop->last ? '' : 'border-bottom' }}">
                            <span class="fw-medium">{{ $version['at'] }}</span>
                            <span class="text-secondary">· admin #{{ $version['actor_id'] ?? '—' }} · {{ count($version['sections'] ?? []) }} section</span>
                        </li>
                    @endforeach
                </ul>
            </x-admin.card>
        @endif
    @endisset

    <x-admin.card title="Tema: Snapshot & Aktivasi Terjadwal" icon="palette" class="mt-3">
        <p class="small text-secondary mb-0">Layanan <code>ThemeManager::snapshot / rollback / duplicate / previewResolve / scheduleActivation / runScheduledActivation / validate</code> siap dipakai. Panel tulis + pratinjau duplikat/rollback perlu wiring route oleh integrator (ThemeController di luar scope edit task ini — service + validasi sudah hijau via test).</p>
    </x-admin.card>

    @isset($popups)
        <x-admin.card title="Popup Konversi" icon="message" class="mt-3">
            <p class="small text-secondary mb-3">Popup tayang sekali per sesi pengunjung (batas tampil ulang mengikuti <em>cap hari</em>). Isi HTML otomatis disanitasi — script &amp; tautan berbahaya dibuang.</p>
            <div class="table-responsive">
                <table class="table admin-table mb-0 table-hover">
                    <thead>
                        <tr>
                            <th scope="col">Judul</th>
                            <th scope="col">Target</th>
                            <th scope="col">Jadwal</th>
                            <th scope="col" class="text-center">Tayang</th>
                            <th scope="col" class="text-center">Klik</th>
                            <th scope="col" class="text-center">Aktif</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($popups as $popup)
                            <tr>
                                <td>
                                    <span class="fw-medium">{{ $popup['title'] ?? '—' }}</span>
                                    @if (! empty($popup['button_text'] ?? null))
                                        <small class="text-secondary d-block">Tombol: {{ $popup['button_text'] }}</small>
                                    @endif
                                </td>
                                <td><x-admin.badge color="info" :text="$popup['targeting'] ?? 'all'" /></td>
                                <td><small class="text-secondary">{{ ($popup['starts_at'] ?? '—').' → '.($popup['ends_at'] ?? '—') }}</small></td>
                                <td class="text-center">{{ number_format((int) ($popup['views_count'] ?? 0), 0, ',', '.') }}</td>
                                <td class="text-center">{{ number_format((int) ($popup['clicks_count'] ?? 0), 0, ',', '.') }}</td>
                                <td class="text-center"><x-admin.badge :color="! empty($popup['is_active']) ? 'success' : 'secondary'" :text="! empty($popup['is_active']) ? 'Aktif' : 'Nonaktif'" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-4 text-secondary">Belum ada popup — buat lewat HomepageController::storePopup (integrator wiring route).</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.card>
    @endisset
@endsection

@push('scripts')
<script>
(function () {
    var trigger = document.getElementById('preview-homepage');
    var body = document.getElementById('preview-body');
    var modalEl = document.getElementById('preview-modal');
    if (!trigger || !body || !modalEl || typeof bootstrap === 'undefined') {
        return;
    }

    trigger.addEventListener('click', function () {
        body.innerHTML = '<p class="text-secondary small mb-0">Memuat data pratinjau…</p>';
        bootstrap.Modal.getOrCreateInstance(modalEl).show();

        fetch(trigger.dataset.previewUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                if (!payload.success) {
                    body.innerHTML = '<p class="text-danger mb-0">Pratinjau tidak dapat dimuat.</p>';
                    return;
                }
                var sections = payload.data.sections || [];
                if (!sections.length) {
                    body.innerHTML = '<p class="text-secondary mb-0">Tidak ada bagian aktif untuk ditampilkan.</p>';
                    return;
                }
                body.innerHTML = sections.map(function (section) {
                    var summary = (section.summary || []).map(function (row) {
                        return '<li class="d-flex justify-content-between"><span class="text-secondary">' + row.label + '</span><span class="fw-semibold">' + row.value + '</span></li>';
                    }).join('');
                    var body = section.empty
                        ? '<p class="small text-warning mb-0">Data kosong — section ini tidak akan menampilkan apa pun.</p>'
                        : '<ul class="list-unstyled small mb-0">' + summary + '</ul>';
                    return '<div class="border rounded-3 p-3 mb-2">' +
                        '<p class="fw-semibold mb-1">' + section.title + '</p>' +
                        (section.subtitle ? '<p class="small text-secondary mb-2">' + section.subtitle + '</p>' : '') +
                        body +
                        '</div>';
                }).join('');
            })
            .catch(function () {
                body.innerHTML = '<p class="text-danger mb-0">Permintaan pratinjau gagal.</p>';
            });
    });
})();
</script>
@endpush
