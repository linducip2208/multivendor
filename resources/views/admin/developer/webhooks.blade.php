@extends('layouts.admin')

@section('title', 'Webhooks')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Developers', ['label' => 'Webhooks']]" />
@endsection

@section('content')
    <x-admin.page-header title="Webhooks" subtitle="Endpoint yang menerima notifikasi peristiwa platform.">
        <x-slot:actions>
            <a href="{{ route('admin.events.index') }}" class="btn btn-outline-secondary btn-sm">Katalog Peristiwa</a>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#webhook-modal" aria-haspopup="dialog">
                <x-admin.icon name="plus" :size="14" /> Tambah Endpoint
            </button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.alert type="info" :title="'Secret dibuat server dan hanya ditampilkan tersamar'" icon="lock">
        Setiap endpoint memiliki secret HMAC-SHA256 yang dirotasi dari halaman ini. Rotasi secret akan membuat endpoint lama berhenti memverifikasi tanda tangan.
    </x-admin.alert>

    <x-admin.card title="Daftar Endpoint" icon="webhook" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Nama</th>
                        <th scope="col">URL</th>
                        <th scope="col">Peristiwa</th>
                        <th scope="col" class="text-center">Kegagalan</th>
                        <th scope="col" class="text-end">Pengiriman</th>
                        <th scope="col">Terakhir Dipicu</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                {{ $row['name'] }}
                                @if ($row['description'] !== '')
                                    <small class="d-block text-secondary">{{ \Illuminate\Support\Str::limit($row['description'], 50) }}</small>
                                @endif
                            </td>
                            <td>
                                <code class="small text-break">{{ $row['url'] }}</code>
                                <small class="d-block text-secondary">{{ $row['host'] }} · {{ $row['secret_masked'] }}</small>
                            </td>
                            <td>
                                @foreach (array_slice($row['events'], 0, 3) as $event)
                                    <x-admin.badge :text="$event" color="info" pill />
                                @endforeach
                                @if (count($row['events']) > 3)
                                    <small class="d-block text-secondary">+{{ count($row['events']) - 3 }} lainnya</small>
                                @endif
                            </td>
                            <td class="text-end">
                                <x-admin.badge :text="number_format($row['failure_count'], 0, ',', '.')" :color="$row['failure_count'] > 0 ? 'danger' : 'secondary'" pill />
                            </td>
                            <td class="text-end">{{ number_format($row['deliveries'], 0, ',', '.') }}</td>
                            <td class="text-nowrap">{{ $row['last_triggered_at'] !== '' ? $row['last_triggered_at'] : '-' }}</td>
                            <td class="text-center">
                                <x-admin.badge :text="$row['is_active'] ? 'Aktif' : 'Nonaktif'" :color="$row['is_active'] ? 'success' : 'secondary'" pill />
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Aksi endpoint {{ $row['name'] }}">
                                    <a href="{{ route('admin.webhooks.deliveries', $row['id']) }}" class="btn btn-outline-secondary">Pengiriman</a>
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#edit-webhook-{{ $row['id'] }}"
                                        aria-label="Ubah endpoint {{ $row['name'] }}"
                                        aria-haspopup="dialog"
                                    >
                                        <x-admin.icon name="pencil" :size="14" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <x-admin.modal :id="'edit-webhook-'.$row['id']" :title="'Ubah '.$row['name']" icon="pencil" size="lg">
                            <form method="POST" action="{{ route('admin.webhooks.update', $row['id']) }}">
                                @csrf
                                @method('PUT')
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <x-admin.form-field name="name" label="Nama" :value="$row['name']" required :maxlength="120" />
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="wh-active-{{ $row['id'] }}">Status</label>
                                        <select class="form-select" id="wh-active-{{ $row['id'] }}" name="is_active">
                                            <option value="1" @selected($row['is_active'])>Aktif</option>
                                            <option value="0" @selected(! $row['is_active'])>Nonaktif</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <x-admin.form-field name="url" label="URL Endpoint" type="url" :value="$row['url']" required :maxlength="500" />
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="wh-events-{{ $row['id'] }}">Peristiwa</label>
                                        <select class="form-select" id="wh-events-{{ $row['id'] }}" name="events[]" multiple size="8" required data-multi-select>
                                            @foreach ($events as $event)
                                                <option value="{{ $event['event'] }}" @selected(in_array($event['event'], $row['events'], true))>{{ $event['event'] }} — {{ $event['description'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <x-admin.form-field name="description" label="Catatan" type="textarea" :rows="2" :value="$row['description']" :maxlength="500" />
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary">Simpan</button>
                                </div>
                            </form>
                        </x-admin.modal>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-admin.empty-state icon="webhook" title="Belum ada webhook" text="Tambahkan endpoint untuk menerima notifikasi peristiwa." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <x-admin.modal id="webhook-modal" title="Tambah Endpoint Webhook" icon="plus" size="lg">
        <form method="POST" action="{{ route('admin.webhooks.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-8">
                    <x-admin.form-field name="name" label="Nama" required :maxlength="120" />
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="wh-new-active">Status</label>
                    <select class="form-select" id="wh-new-active" name="is_active">
                        <option value="1" selected>Aktif</option>
                        <option value="0">Nonaktif</option>
                    </select>
                </div>
                <div class="col-12">
                    <x-admin.form-field name="url" label="URL Endpoint" type="url" required :maxlength="500" placeholder="https://example.com/webhooks/platform" />
                </div>
                <div class="col-12">
                    <label class="form-label" for="wh-new-events">Peristiwa</label>
                    <select class="form-select" id="wh-new-events" name="events[]" multiple size="8" required data-multi-select>
                        @foreach ($events as $event)
                            <option value="{{ $event['event'] }}">{{ $event['event'] }} — {{ $event['description'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <x-admin.form-field name="description" label="Catatan" type="textarea" :rows="2" :maxlength="500" />
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Endpoint</button>
            </div>
        </form>
    </x-admin.modal>
@endsection
