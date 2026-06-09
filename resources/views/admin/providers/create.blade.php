@extends('layouts.admin')

@section('title', 'Tambah Provider')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.providers.index') }}" class="text-decoration-none small"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
    <h4 class="fw-bold mt-2 mb-1">Tambah Provider</h4>
    <p class="text-muted small">Konfigurasi payment gateway, shipping, atau integrasi lainnya</p>
</div>

<div class="card border-0 rounded-4 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.providers.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-medium">Nama Provider <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="Midtrans, Xendit, RajaOngkir..." required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Tipe <span class="text-danger">*</span></label>
                    <select name="type" class="form-select" id="providerType" required>
                        <option value="">Pilih Tipe</option>
                        <option value="payment" {{ old('type') === 'payment' ? 'selected' : '' }}>Payment Gateway</option>
                        <option value="shipping" {{ old('type') === 'shipping' ? 'selected' : '' }}>Shipping / Kurir</option>
                        <option value="ai" {{ old('type') === 'ai' ? 'selected' : '' }}>AI / LLM</option>
                        <option value="sms" {{ old('type') === 'sms' ? 'selected' : '' }}>SMS Gateway</option>
                        <option value="mail" {{ old('type') === 'mail' ? 'selected' : '' }}>Mail Provider</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Format API <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <select name="api_format" class="form-select" id="apiFormat" required>
                            <option value="">Pilih Format</option>
                        </select>
                        <button type="button" class="btn btn-outline-secondary" id="autofillBtn" title="Autofill dari preset">
                            <i class="fas fa-magic"></i>
                        </button>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-medium">Base URL</label>
                    <input type="url" name="base_url" class="form-control" value="{{ old('base_url') }}" id="baseUrl" placeholder="https://api.example.com">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium"><span id="apiKeyLabel">API Key</span></label>
                    <input type="text" name="api_key" class="form-control" value="{{ old('api_key') }}" placeholder="Masukkan API key...">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium"><span id="apiSecretLabel">Secret Key / Password</span></label>
                    <input type="text" name="api_secret" class="form-control" value="{{ old('api_secret') }}" placeholder="Masukkan secret key...">
                </div>
                <div class="col-12">
                    <label class="form-label fw-medium">Extra Headers (JSON)</label>
                    <textarea name="extra_headers" class="form-control font-monospace small" rows="3" placeholder='{"X-Custom-Header": "value"}'>{{ old('extra_headers') }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-medium">Config Tambahan (JSON)</label>
                    <textarea name="config" class="form-control font-monospace small" rows="3" id="configField" placeholder='{"merchant_code": "XXX"}'>{{ old('config') }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-medium">Deskripsi</label>
                    <textarea name="description" class="form-control" rows="2" id="descriptionField">{{ old('description') }}</textarea>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch mt-4">
                        <input type="checkbox" name="is_active" class="form-check-input" id="isActive" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                        <label class="form-check-label fw-medium" for="isActive">Provider Aktif</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i> Simpan Provider</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('head')
@php $presetsJson = json_encode($presets); @endphp
@endpush

@push('scripts')
<script>
const presets = {!! $presetsJson !!};

const formatOptions = {
    payment: [
        {value: 'midtrans-snap', label: 'Midtrans Snap (Redirect/Popup)'},
        {value: 'midtrans-core', label: 'Midtrans Core API (VA/QR Manual)'},
        {value: 'xendit-invoice', label: 'Xendit Invoice'},
        {value: 'tripay-closed', label: 'Tripay Closed Transaction'},
        {value: 'duitku-redirect', label: 'Duitku Redirect'},
        {value: 'oyindonesia-api', label: 'OY! Indonesia API'},
        {value: 'ipaymu-api', label: 'iPaymu API'},
        {value: 'faspay-api', label: 'Faspay API'},
        {value: 'doku-api', label: 'DOKU API'},
        {value: 'esiapay-api', label: 'ESIA Pay API'}
    ],
    shipping: [
        {value: 'rajaongkir-starter', label: 'RajaOngkir Starter'},
        {value: 'rajaongkir-pro', label: 'RajaOngkir Pro'},
        {value: 'courier-rest', label: 'Courier REST API (Generic)'}
    ],
    ai: [
        {value: 'openai-compatible', label: 'OpenAI-Compatible (DeepSeek, Groq, OpenRouter, Ollama, dll.)'},
        {value: 'anthropic-format', label: 'Anthropic Messages API (Claude)'},
        {value: 'gemini-format', label: 'Google Gemini API'}
    ]
};

document.getElementById('providerType').addEventListener('change', function() {
    const type = this.value;
    const formatSelect = document.getElementById('apiFormat');
    formatSelect.innerHTML = '<option value="">Pilih Format</option>';
    if (formatOptions[type]) {
        formatOptions[type].forEach(opt => {
            formatSelect.innerHTML += `<option value="${opt.value}">${opt.label}</option>`;
        });
    }
});

document.getElementById('autofillBtn').addEventListener('click', function() {
    const type = document.getElementById('providerType').value;
    const format = document.getElementById('apiFormat').value;
    const key = type === 'payment' ? 'payment-presets' : (type === 'shipping' ? 'shipping-presets' : 'ai-presets');
    const list = presets[key] || [];

    const preset = list.find(p => p.api_format === format);
    if (preset) {
        document.getElementById('baseUrl').value = preset.base_url || '';
        document.getElementById('descriptionField').value = preset.description || '';
        document.getElementById('apiKeyLabel').textContent = preset.fields?.api_key_label || 'API Key';
        document.getElementById('apiSecretLabel').textContent = preset.fields?.api_secret_label || 'Secret Key';
        if (preset.fields?.merchant_code_label) {
            document.getElementById('configField').placeholder = '{"merchant_code": "' + (preset.fields.merchant_code_label || '') + '"}';
        }
        alert('Preset terisi! Silakan masukkan API key Anda sendiri.');
    } else {
        alert('Tidak ada preset untuk format ini. Isi manual.');
    }
});
</script>
@endpush
