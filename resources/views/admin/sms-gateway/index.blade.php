@extends('layouts.admin')

@section('title', 'SMS Gateway')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Sistem', ['label' => 'SMS']]" />
@endsection

@section('content')
    <x-admin.page-header title="SMS Gateway" subtitle="Kanal SMS untuk OTP dan pemberitahuan pesanan." />

    <x-admin.alert type="info" :dismissible="false" title="Kredensial bersifat tulis-saja" icon="lock">
        Nilai API key dan secret tidak pernah dikirim kembali ke peramban. Kolom kosong berarti kredensial lama dipertahankan.
    </x-admin.alert>

    <form method="POST" action="{{ route('admin.sms-gateway.update') }}">
        @csrf
        @method('PUT')
        <x-admin.card title="Konfigurasi" icon="message-square">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="sms-provider">Provider</label>
                    <select class="form-select" id="sms-provider" name="sms_provider" required>
                        @foreach ($providers as $value => $label)
                            <option value="{{ $value }}" @selected($settings['sms_provider'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <x-admin.form-field name="sms_sender_id" label="Sender ID" :value="$settings['sms_sender_id']" :maxlength="40" />
                </div>
                <div class="col-12 col-md-4">
                    <x-admin.form-field
                        name="sms_api_key"
                        label="API Key / SID"
                        type="password"
                        autocomplete="off"
                        :maxlength="200"
                        :placeholder="$settings['sms_has_api_key'] ? 'Tersimpan — tulis untuk mengganti' : 'Belum diisi'"
                    />
                </div>
                <div class="col-12 col-md-4">
                    <x-admin.form-field
                        name="sms_api_secret"
                        label="API Secret / Token"
                        type="password"
                        autocomplete="off"
                        :maxlength="200"
                        :placeholder="$settings['sms_has_api_secret'] ? 'Tersimpan — tulis untuk mengganti' : 'Belum diisi'"
                    />
                </div>
                <div class="col-12 col-md-6">
                    <x-admin.form-field
                        name="sms_template_order"
                        label="Template SMS Pesanan"
                        type="textarea"
                        :rows="2"
                        :value="$settings['sms_template_order']"
                        :maxlength="500"
                    />
                </div>
                <div class="col-12 col-md-6">
                    <x-admin.form-field
                        name="sms_template_otp"
                        label="Template SMS OTP"
                        type="textarea"
                        :rows="2"
                        :value="$settings['sms_template_otp']"
                        :maxlength="500"
                    />
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary">
                    <x-admin.icon name="save" :size="14" /> Simpan Gateway
                </button>
            </div>
        </x-admin.card>
    </form>

    @php
        $waProvider = (string) (\App\Models\SystemSetting::get('sms_provider', 'none') ?? 'none');
        $waSiap = $waProvider !== 'none' && (string) (\App\Models\SystemSetting::get('sms_api_key', '') ?? '') !== '';
    @endphp
    <x-admin.card title="Kanal WhatsApp" icon="message-circle" class="mt-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <x-admin.badge :text="$waSiap ? 'Siap via '.$waProvider : 'Fallback log'" :color="$waSiap ? 'success' : 'warning'" pill />
            <span class="small text-secondary">
                Notifikasi WA memakai kredensial sms-gateway di atas.
                {{ $waSiap ? 'Pesan dikirim memakai provider '.$waProvider.'.' : 'Provider belum dikonfigurasi — pesan dicatat ke log + pusat notifikasi (channel whatsapp) beserta statusnya.' }}
            </span>
        </div>
    </x-admin.card>
@endsection
