@extends('layouts.admin')

@section('title', 'Tambah Provider')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Sistem', ['label' => 'Provider', 'href' => route('admin.providers.index')], ['label' => 'Baru']]" />
@endsection

@section('content')
    <x-admin.page-header title="Tambah Provider" subtitle="Format API divalidasi terhadap adapter yang benar-benar tersedia." />

    <form method="POST" action="{{ route('admin.providers.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-lg-7">
                <x-admin.card title="Konfigurasi" icon="plug">
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <x-admin.form-field name="name" label="Nama Provider" required :maxlength="255" />
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="provider-type">Tipe</label>
                            <select class="form-select" id="provider-type" name="type" required>
                                @foreach ($types as $type)
                                    <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="provider-format">Format API</label>
                            <select class="form-select" id="provider-format" name="api_format" required>
                                @foreach ($formats as $type => $allowed)
                                    <optgroup label="{{ ucfirst($type) }}">
                                        @foreach ($allowed as $format)
                                            <option value="{{ $format }}">{{ $format }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <small class="text-secondary">Hanya format yang didukung layer adapter untuk tipe terpilih.</small>
                        </div>
                        <div class="col-12 col-md-6">
                            <x-admin.form-field name="base_url" label="Base URL" type="url" :maxlength="500" placeholder="https://api.example.com" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-admin.form-field name="api_key" label="API Key" type="password" :maxlength="1000" autocomplete="off" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-admin.form-field name="api_secret" label="API Secret" type="password" :maxlength="1000" autocomplete="off" />
                        </div>
                        <div class="col-12">
                            <x-admin.form-field name="description" label="Deskripsi" type="textarea" :rows="2" :maxlength="1000" />
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
                                :maxlength="4000"
                                placeholder='{"X-Custom":"value"}'
                            />
                        </div>
                        <div class="col-12">
                            <x-admin.form-field
                                name="config"
                                label="Konfigurasi (JSON)"
                                type="textarea"
                                :rows="5"
                                :maxlength="4000"
                                placeholder='{"default_model":"gpt-4o-mini"}'
                            />
                        </div>
                        <div class="col-12 d-flex gap-3">
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="provider-active" checked>
                                <label class="form-check-label" for="provider-active">Aktif</label>
                            </div>
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_default" value="0">
                                <input class="form-check-input" type="checkbox" name="is_default" value="1" id="provider-default">
                                <label class="form-check-label" for="provider-default">Provider bawaan</label>
                            </div>
                        </div>
                    </div>
                </x-admin.card>

                <x-admin.card title="Preset" icon="zap" subtitle="Format cepat dari berkas preset bawaan.">
                    <ul class="list-unstyled small mb-0">
                        @foreach (['payment-presets', 'shipping-presets', 'ai-presets'] as $key)
                            @php $list = $presets[$key] ?? []; @endphp
                            <li class="mb-1">
                                <strong>{{ $key }}</strong>: {{ $list === [] ? 'tidak tersedia' : count($list).' preset' }}
                            </li>
                        @endforeach
                    </ul>
                </x-admin.card>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="{{ route('admin.providers.index') }}" class="btn btn-outline-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan dan Uji Koneksi</button>
        </div>
    </form>
@endsection
