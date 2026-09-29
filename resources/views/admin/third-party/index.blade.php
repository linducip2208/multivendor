@extends('layouts.admin')

@section('title', 'Integrasi Pihak Ketiga')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['System', ['label' => 'Integrasi']]" />
@endsection

@section('content')
    <x-admin.page-header title="Integrasi Pihak Ketiga" subtitle="Layanan pihak ketiga yang disematkan di storefront." />

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <x-admin.card title="reCAPTCHA" icon="shield" class="h-100">
                <form method="POST" action="{{ route('admin.third-party.update') }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="section" value="recaptcha">
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="recaptcha_enabled" value="0">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="recaptcha_enabled"
                            value="1"
                            id="recaptcha-enabled"
                            @checked($recaptcha['enabled'])
                        >
                        <label class="form-check-label" for="recaptcha-enabled">Aktifkan reCAPTCHA</label>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="recaptcha-site">Site Key</label>
                        <input type="text" class="form-control form-control-sm" id="recaptcha-site" name="recaptcha_site_key" value="{{ $recaptcha['site_key'] }}" maxlength="200">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="recaptcha-secret">Secret Key</label>
                        <input
                            type="password"
                            class="form-control form-control-sm"
                            id="recaptcha-secret"
                            name="recaptcha_secret_key"
                            maxlength="200"
                            autocomplete="off"
                            placeholder="{{ $recaptcha['has_secret'] ? 'Tersimpan — tulis untuk mengganti' : 'Belum diisi' }}"
                        >
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
                </form>
            </x-admin.card>
        </div>

        <div class="col-12 col-xl-6">
            <x-admin.card title="Peta" icon="map-pin" class="h-100">
                <form method="POST" action="{{ route('admin.third-party.update') }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="section" value="map">
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="map-provider">Provider Peta</label>
                        <select class="form-select form-select-sm" id="map-provider" name="map_provider">
                            @foreach (['google' => 'Google Maps', 'mapbox' => 'Mapbox', 'openstreetmap' => 'OpenStreetMap'] as $value => $label)
                                <option value="{{ $value }}" @selected($map['provider'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="map-key">API Key</label>
                        <input type="password" class="form-control form-control-sm" id="map-key" name="map_api_key" value="{{ $map['api_key'] }}" maxlength="200" autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
                </form>
            </x-admin.card>
        </div>

        <div class="col-12 col-xl-6">
            <x-admin.card title="Media Sosial" icon="share-2" class="h-100">
                <form method="POST" action="{{ route('admin.third-party.update') }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="section" value="social">
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="wa-number">Nomor WhatsApp</label>
                        <input type="text" class="form-control form-control-sm" id="wa-number" name="whatsapp_number" value="{{ $social['whatsapp_number'] }}" maxlength="40">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="wa-message">Pesan Default</label>
                        <input type="text" class="form-control form-control-sm" id="wa-message" name="whatsapp_message" value="{{ $social['whatsapp_message'] }}" maxlength="255">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="fb-page">Facebook Page ID</label>
                        <input type="text" class="form-control form-control-sm" id="fb-page" name="fb_page_id" value="{{ $social['fb_page_id'] }}" maxlength="120">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
                </form>
            </x-admin.card>
        </div>

        <div class="col-12 col-xl-6">
            <x-admin.card title="Analytics" icon="bar-chart" class="h-100">
                <form method="POST" action="{{ route('admin.third-party.update') }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="section" value="analytics">
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="ga-id">Google Analytics ID</label>
                        <input type="text" class="form-control form-control-sm" id="ga-id" name="ga_id" value="{{ $analytics['ga_id'] }}" maxlength="60" placeholder="G-XXXXXXXXXX">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="pixel-id">Facebook Pixel ID</label>
                        <input type="text" class="form-control form-control-sm" id="pixel-id" name="fb_pixel_id" value="{{ $analytics['fb_pixel_id'] }}" maxlength="60">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="gtm-id">Google Tag Manager ID</label>
                        <input type="text" class="form-control form-control-sm" id="gtm-id" name="gtm_id" value="{{ $analytics['gtm_id'] }}" maxlength="60" placeholder="GTM-XXXXXXX">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
                </form>
            </x-admin.card>
        </div>
    </div>
@endsection
