@extends('layouts.admin')

@section('title', 'Mata Uang')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Sistem', ['label' => 'Mata Uang']]" />
@endsection

@section('content')
    <x-admin.page-header title="Mata Uang" subtitle="Format tampilan nilai uang di seluruh antarmuka." />

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <form method="POST" action="{{ route('admin.currency.update') }}">
                @csrf
                @method('PUT')
                <x-admin.card title="Format Mata Uang" icon="cash">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <x-admin.form-field name="currency_code" label="Kode Mata Uang" :value="$settings['currency_code']" :maxlength="3" required />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-admin.form-field name="currency_symbol" label="Simbol" :value="$settings['currency_symbol']" :maxlength="5" required />
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="symbol-position">Posisi Simbol</label>
                            <select class="form-select" id="symbol-position" name="symbol_position" required>
                                <option value="left" @selected($settings['symbol_position'] === 'left')>Kiri ({{ $settings['currency_symbol'] }} 100.000)</option>
                                <option value="right" @selected($settings['symbol_position'] === 'right')>Kanan (100.000 {{ $settings['currency_symbol'] }})</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <x-admin.form-field name="decimal_point" label="Jumlah Desimal" type="number" :value="$settings['decimal_point']" :min="0" :max="4" required />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-admin.form-field name="thousand_separator" label="Pemisah Ribuan" :value="$settings['thousand_separator']" :maxlength="2" required />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-admin.form-field name="decimal_separator" label="Pemisah Desimal" :value="$settings['decimal_separator']" :maxlength="2" required />
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary">
                            <x-admin.icon name="save" :size="14" /> Simpan Format
                        </button>
                    </div>
                </x-admin.card>
            </form>
        </div>

        <div class="col-12 col-lg-5">
            <x-admin.card title="Pratinjau" icon="eye" subtitle="Contoh render dengan format saat ini." class="h-100">
                <dl class="row small mb-0">
                    <dt class="col-6 text-secondary">Contoh saat ini</dt>
                    <dd class="col-6 text-end fw-semibold">{{ $preview }}</dd>
                    <dt class="col-6 text-secondary">Nol</dt>
                    <dd class="col-6 text-end">{{ \App\Support\Currency::format(0) }}</dd>
                    <dt class="col-6 text-secondary">Nilai negatif</dt>
                    <dd class="col-6 text-end">{{ \App\Support\Currency::format(-125000) }}</dd>
                </dl>
            </x-admin.card>
        </div>
    </div>
@endsection
