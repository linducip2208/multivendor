@extends('layouts.admin')

@section('title', 'Settlement '.$settlement['vendor'])

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Finance', ['label' => 'Settlement', 'href' => route('admin.settlements.index')], ['label' => '#'.$settlement['id']]]" />
@endsection

@section('content')
    <x-admin.page-header :title="$settlement['vendor']" :subtitle="$settlement['shop'].' · diajukan '.$settlement['created_at']">
        <x-slot:actions>
            <a href="{{ route('admin.settlements.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Nominal Diminta" :value="$settlement['amount']" money icon="download" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total Pendapatan Mitra" :value="$earnings['lifetime']" money icon="trending-up" color="success" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Komisi TerPotong" :value="$earnings['commission']" money icon="percent" color="warning" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Porsi Diminta" :value="number_format($earnings['requested_percent'], 1, ',', '.').'%'" icon="percent" color="info" :hint="number_format($earnings['orders'], 0, ',', '.').' transaksi tercatat'" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <x-admin.card title="Detail Permintaan" icon="info" class="mb-3">
                <dl class="row small mb-0">
                    <dt class="col-5 text-secondary">Vendor</dt>
                    <dd class="col-7 text-end">{{ $settlement['vendor'] }}</dd>
                    <dt class="col-5 text-secondary">Email</dt>
                    <dd class="col-7 text-end text-break">{{ $settlement['vendor_email'] }}</dd>
                    <dt class="col-5 text-secondary">Toko</dt>
                    <dd class="col-7 text-end">
                        {{ $settlement['shop'] }}
                        <x-admin.badge :text="$settlement['shop_status']" :color="$settlement['shop_status'] === 'active' ? 'success' : 'secondary'" pill />
                    </dd>
                    <dt class="col-5 text-secondary">Bank</dt>
                    <dd class="col-7 text-end">{{ $settlement['bank_name'] !== '' ? $settlement['bank_name'] : '-' }}</dd>
                    <dt class="col-5 text-secondary">Nomor rekening</dt>
                    <dd class="col-7 text-end"><code>{{ $settlement['bank_account'] }}</code></dd>
                    <dt class="col-5 text-secondary">Atas nama</dt>
                    <dd class="col-7 text-end">{{ $settlement['bank_account_name'] !== '' ? $settlement['bank_account_name'] : '-' }}</dd>
                    <dt class="col-5 text-secondary">Catatan vendor</dt>
                    <dd class="col-7 text-end">{{ $settlement['note'] !== '' ? $settlement['note'] : '-' }}</dd>
                </dl>
            </x-admin.card>

            <x-admin.card title="Riwayat Status" icon="clock">
                <x-admin.timeline
                    :items="[
                        ['title' => 'Diajukan', 'meta' => $settlement['created_at'], 'body' => null, 'state' => 'done'],
                        ['title' => $settlement['approved_at'] !== '' ? 'Disetujui' : 'Belum disetujui', 'meta' => $settlement['approved_at'] !== '' ? $settlement['approved_at'] : null, 'body' => $settlement['approver'] !== '' ? 'Disetujui oleh '.$settlement['approver'] : null, 'state' => $settlement['approved_at'] !== '' ? 'done' : 'pending'],
                        ['title' => $settlement['completed_at'] !== '' ? 'Dana dikirim' : 'Belum dikirim', 'meta' => $settlement['completed_at'] !== '' ? $settlement['completed_at'] : null, 'body' => $settlement['rejection_reason'] !== '' ? 'Ditolak: '.$settlement['rejection_reason'] : null, 'state' => $settlement['completed_at'] !== '' ? 'done' : 'pending'],
                    ]"
                />
            </x-admin.card>
        </div>

        <div class="col-lg-6">
            <x-admin.card title="Ringkasan Mitra" icon="store">
                <x-admin.alert type="info" :dismissible="false" title="Nomor rekening disamarkan">
                    Empat digit terakhir ditampilkan untuk verifikasi; angka lengkap hanya tersedia pada alur pembayaran.
                </x-admin.alert>
                <dl class="row small mb-0">
                    <dt class="col-7 text-secondary">Transaksi berhasil</dt>
                    <dd class="col-5 text-end">{{ number_format($earnings['orders'], 0, ',', '.') }}</dd>
                    <dt class="col-7 text-secondary">Nilai berhasil terjual</dt>
                    <dd class="col-5 text-end fw-semibold">{{ $earnings['lifetime_formatted'] }}</dd>
                    <dt class="col-7 text-secondary">Komisi platform</dt>
                    <dd class="col-5 text-end">{{ $earnings['commission_formatted'] }}</dd>
                    <dt class="col-7 text-secondary">Diminta pada permintaan ini</dt>
                    <dd class="col-5 text-end">{{ $settlement['amount_formatted'] }}</dd>
                </dl>
            </x-admin.card>
        </div>
    </div>
@endsection
