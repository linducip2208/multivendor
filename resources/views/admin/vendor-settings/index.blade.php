@extends('layouts.admin')

@section('title', 'Pengaturan Vendor')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Penjual', ['label' => 'Vendor']]" />
@endsection

@section('content')
    <x-admin.page-header title="Pengaturan Vendor" subtitle="Aturan pendaftaran, komisi, dan pencairan saldo." />

    <form method="POST" action="{{ route('admin.vendor-settings.update') }}">
        @csrf
        @method('PUT')
        <x-admin.card title="Pendaftaran" icon="store">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="form-check form-switch h-100">
                        <input type="hidden" name="vendor_registration_open" value="0">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="vendor_registration_open"
                            value="1"
                            id="registration-open"
                            @checked($settings['vendor_registration_open'])
                        >
                        <label class="form-check-label" for="registration-open">Buka pendaftaran vendor</label>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="form-check form-switch h-100">
                        <input type="hidden" name="vendor_auto_approve" value="0">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="vendor_auto_approve"
                            value="1"
                            id="auto-approve"
                            @checked($settings['vendor_auto_approve'])
                        >
                        <label class="form-check-label" for="auto-approve">Setujui pendaftaran otomatis</label>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <x-admin.form-field
                        name="vendor_default_commission"
                        label="Komisi Default (%)"
                        type="number"
                        :value="$settings['vendor_default_commission']"
                        :min="0"
                        :max="100"
                        :step="0.01"
                        required
                    />
                </div>
                <div class="col-12 col-md-4">
                    <x-admin.form-field
                        name="vendor_min_withdraw"
                        label="Minimum Pencairan"
                        type="number"
                        :value="$settings['vendor_min_withdraw']"
                        :min="0"
                        :step="1000"
                        required
                    />
                </div>
                <div class="col-12 col-md-4">
                    <x-admin.form-field
                        name="vendor_payout_days"
                        label="Jeda Settlement (hari)"
                        type="number"
                        :value="$settings['vendor_payout_days']"
                        :min="0"
                        :max="90"
                        required
                    />
                </div>
                <div class="col-12 col-md-4">
                    <x-admin.form-field
                        name="vendor_min_products"
                        label="Minimum Produk per Toko"
                        type="number"
                        :value="$settings['vendor_min_products']"
                        :min="0"
                        required
                    />
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary">
                    <x-admin.icon name="save" :size="14" /> Simpan Pengaturan
                </button>
            </div>
        </x-admin.card>
    </form>
@endsection
