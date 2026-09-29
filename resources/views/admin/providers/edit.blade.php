@extends('layouts.admin')

@section('title', 'Ubah '.$provider['name'])

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Sistem', ['label' => 'Provider', 'href' => route('admin.providers.index')], ['label' => $provider['name']]]" />
@endsection

@section('content')
    <x-admin.page-header :title="'Ubah '.$provider['name']" :subtitle="$provider['type'].' · '.$provider['api_format']">
        <x-slot:actions>
            <a href="{{ route('admin.providers.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.alert type="info" :dismissible="false" title="Kredensial tidak pernah ditampilkan" icon="lock">
        Nilai saat ini hanya tersimpan sebagai topi. Mengosongkan kolom kredensial berarti kredensial lama dipertahankan.
    </x-admin.alert>

    <form method="POST" action="{{ route('admin.providers.update', $provider['id']) }}">
        @csrf
        @method('PUT')
        <div class="row g-3">
            <div class="col-lg-7">
                <x-admin.card title="Konfigurasi" icon="plug">
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <x-admin.form-field name="name" label="Nama Provider" :value="$provider['name']" required :maxlength="255" />
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="provider-type">Tipe</label>
                            <select class="form-select" id="provider-type" name="type" required>
                                @foreach ($types as $type)
                                    <option value="{{ $type }}" @selected($provider['type'] === $type)>{{ ucfirst($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="provider-format">Format API</label>
                            <select class="form-select" id="provider-format" name="api_format" required>
                                @foreach ($formats as $type => $allowed)
                                    <optgroup label="{{ ucfirst($type) }}">
                                        @foreach ($allowed as $format)
                                            <option value="{{ $format }}" @selected($provider['api_format'] === $format)>{{ $format }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <x-admin.form-field name="base_url" label="Base URL" type="url" :value="$provider['base_url']" :maxlength="500" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-admin.form-field
                                name="api_key"
                                label="API Key Baru"
                                type="password"
                                :maxlength="1000"
                                autocomplete="off"
                                :placeholder="$provider['has_key'] ? $provider['api_key_mask'] : 'Belum diisi'"
                            />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-admin.form-field
                                name="api_secret"
                                label="API Secret Baru"
                                type="password"
                                :maxlength="1000"
                                autocomplete="off"
                                :placeholder="$provider['has_secret'] ? $provider['api_secret_mask'] : 'Belum diisi'"
                            />
                        </div>
                        <div class="col-12">
                            <x-admin.form-field name="description" label="Deskripsi" type="textarea" :rows="2" :value="$provider['description']" :maxlength="1000" />
                        </div>
                    </div>
                </x-admin.card>
            </div>

            <div class="col-lg-5">
                <x-admin.card title="Tambahan" icon="settings" class="mb-3">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-admin.form-field
                                name="extra_headers"
                                label="Header Tambahan (JSON)"
                                type="textarea"
                                :rows="3"
                                :value="json_encode($provider['extra_headers'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)"
                                :maxlength="4000"
                            />
                        </div>
                        <div class="col-12">
                            <x-admin.form-field
                                name="config"
                                label="Konfigurasi (JSON)"
                                type="textarea"
                                :rows="5"
                                :value="json_encode($provider['config'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)"
                                :maxlength="4000"
                            />
                        </div>
                        <div class="col-12 d-flex gap-3">
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="provider-active" @checked($provider['is_active'])>
                                <label class="form-check-label" for="provider-active">Aktif</label>
                            </div>
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_default" value="0">
                                <input class="form-check-input" type="checkbox" name="is_default" value="1" id="provider-default" @checked($provider['is_default'])>
                                <label class="form-check-label" for="provider-default">Provider bawaan</label>
                            </div>
                        </div>
                    </div>
                </x-admin.card>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="{{ route('admin.providers.index') }}" class="btn btn-outline-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan dan Uji Koneksi</button>
        </div>
    </form>
@endsection
