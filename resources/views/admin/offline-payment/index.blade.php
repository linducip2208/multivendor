@extends('layouts.admin')

@section('title', 'Metode Pembayaran Offline')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['System', ['label' => 'Pembayaran Offline']]" />
@endsection

@section('content')
    <x-admin.page-header title="Metode Pembayaran Offline" subtitle="Metode yang diselesaikan di luar gateway otomatis." />

    <form method="POST" action="{{ route('admin.offline-payment.update') }}">
        @csrf
        @method('PUT')
        <div class="row g-3">
            <div class="col-12 col-xl-9">
                <x-admin.card title="Metode" icon="credit-card">
                    <div class="row g-3">
                        @foreach ($methods as $method)
                            <div class="col-12 col-lg-4">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <p class="fw-semibold mb-0">{{ $method['label'] }}</p>
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="methods[{{ $method['key'] }}][active]" value="0">
                                            <input
                                                type="checkbox"
                                                class="form-check-input"
                                                name="methods[{{ $method['key'] }}][active]"
                                                value="1"
                                                id="method-{{ $method['key'] }}-active"
                                                @checked($method['active'])
                                            >
                                            <label class="form-check-label visually-hidden" for="method-{{ $method['key'] }}-active">Aktifkan {{ $method['label'] }}</label>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small mb-1" for="method-{{ $method['key'] }}-details">Informasi Rekening</label>
                                        <textarea
                                            class="form-control form-control-sm"
                                            id="method-{{ $method['key'] }}-details"
                                            name="methods[{{ $method['key'] }}][details]"
                                            rows="3"
                                            maxlength="500"
                                            placeholder="Nomor rekening, nama bank, atau catatan singkat"
                                        >{{ $method['details'] }}</textarea>
                                    </div>
                                    <div>
                                        <label class="form-label small mb-1" for="method-{{ $method['key'] }}-instructions">Instruksi Pembayaran</label>
                                        <textarea
                                            class="form-control form-control-sm"
                                            id="method-{{ $method['key'] }}-instructions"
                                            name="methods[{{ $method['key'] }}][instructions]"
                                            rows="4"
                                            maxlength="2000"
                                            placeholder="Langkah yang harus dilakukan pelanggan"
                                        >{{ $method['instructions'] }}</textarea>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-admin.card>
            </div>

            <div class="col-12 col-xl-3">
                <x-admin.card title="Umum" icon="settings">
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="requires_proof" value="0">
                        <input class="form-check-input" type="checkbox" name="requires_proof" value="1" id="requires-proof" @checked($requires_proof)>
                        <label class="form-check-label" for="requires-proof">Wajibkan bukti transfer</label>
                    </div>
                    <x-admin.form-field
                        name="expiry_hours"
                        label="Masa Berlaku Pembayaran (jam)"
                        type="number"
                        :value="$expiry_hours"
                        :min="1"
                        :max="720"
                    />
                </x-admin.card>
            </div>
        </div>

        <div class="d-flex justify-content-end mt-3">
            <button type="submit" class="btn btn-primary">
                <x-admin.icon name="save" :size="14" /> Simpan Metode
            </button>
        </div>
    </form>
@endsection
