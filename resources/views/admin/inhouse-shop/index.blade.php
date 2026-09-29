@extends('layouts.admin')

@section('title', 'Toko Internal')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Penjual', ['label' => 'Toko Internal']]" />
@endsection

@section('content')
    <x-admin.page-header title="Toko Inhouse" subtitle="Jual produk sendiri di luar jaringan vendor." />

    <form method="POST" action="{{ route('admin.inhouse-shop.update') }}">
        @csrf
        @method('PUT')
        <x-admin.card title="Pengaturan Toko" icon="store">
            <div class="row g-3">
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="hidden" name="inhouse_shop_active" value="0">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="inhouse_shop_active"
                            value="1"
                            id="inhouse-active"
                            @checked($settings['inhouse_shop_active'])
                        >
                        <label class="form-check-label" for="inhouse-active">Aktifkan toko inhouse</label>
                        <small class="form-text">Administrator dapat menjual produk tanpa melibatkan vendor.</small>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <x-admin.form-field name="inhouse_shop_name" label="Nama Toko" :value="$settings['inhouse_shop_name']" required :maxlength="160" />
                </div>
                <div class="col-12 col-md-6">
                    <x-admin.form-field
                        name="inhouse_shop_commission"
                        label="Komisi Toko Inhouse (%)"
                        type="number"
                        :value="$settings['inhouse_shop_commission']"
                        :min="0"
                        :max="100"
                        :step="0.01"
                    />
                </div>
                <div class="col-12">
                    <x-admin.form-field
                        name="inhouse_shop_description"
                        label="Deskripsi"
                        type="textarea"
                        :rows="3"
                        :value="$settings['inhouse_shop_description']"
                        :maxlength="1000"
                    />
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary">
                    <x-admin.icon name="save" :size="14" /> Simpan
                </button>
            </div>
        </x-admin.card>
    </form>
@endsection
