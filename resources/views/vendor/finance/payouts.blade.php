@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Pencairan dana')
@section('subtitle', 'Permintaan dan riwayat penarikan saldo')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Keuangan'],
])

@section('content')
    <x-admin.tabs class="mb-3" :tabs="[
        ['label' => 'Pendapatan', 'href' => route('vendor.finance.revenue'), 'icon' => 'wallet'],
        ['label' => 'Komisi', 'href' => route('vendor.finance.commission'), 'icon' => 'percent'],
        ['label' => 'Pencairan', 'href' => route('vendor.finance.payouts'), 'active' => true, 'icon' => 'cash-coin'],
    ]" />

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl">
            <x-admin.stat label="Saldo tersedia" :value="$available->toFloat()" icon="wallet" color="success" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Sedang diproses" :value="$pending->toFloat()" icon="clock" color="warning" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Sudah dibayar" :value="$paid->toFloat()" icon="check-circle" color="primary" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Ditolak" :value="$rejected->toFloat()" icon="alert-circle" color="danger" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-admin.card title="Permintaan pencairan" icon="cash-coin" :padding="false">
                <x-admin.table dense>
                    <x-slot:table>
                        \App\Support\TableBuilder::make()
                            ->columns([
                                'amount' => ['label' => 'Nominal'],
                                'bank' => ['label' => 'Rekening tujuan'],
                                'status' => ['label' => 'Status'],
                                'requested' => ['label' => 'Diajukan', 'align' => 'end'],
                                'processed' => ['label' => 'Diproses', 'align' => 'end'],
                            ])
                            ->rows(
                                $requests->getCollection()->map(fn ($request) => [
                                    'amount' => '<span class="fw-medium">'.e(Currency::format($request->amount)).'</span>',
                                    'bank' => '<span class="d-block text-truncate">'.e($request->bank_name).'</span><span class="text-secondary small font-monospace">'.e($__maskAccount($request->bank_account_number)).'</span>',
                                    'status' => $__status($request->status),
                                    'requested' => '<span class="text-secondary small">'.e(\Carbon\Carbon::parse($request->created_at)->format('d/m/Y')).'</span>',
                                    'processed' => '<span class="text-secondary small">'.e($request->processed_at ? \Carbon\Carbon::parse($request->processed_at)->format('d/m/Y') : '—').'</span>',
                                ])->all()
                            )
                            ->empty('Belum ada permintaan pencairan.')
                    </x-slot:table>
                </x-admin.table>

                <x-admin.pagination :paginator="$requests" class="mt-3" />
            </x-admin.card>

            <x-admin.card title="Mutasi dompet" icon="activity" class="mt-3" :padding="false">
                <x-admin.table dense>
                    <x-slot:table>
                        \App\Support\TableBuilder::make()
                            ->columns([
                                'description' => ['label' => 'Keterangan'],
                                'type' => ['label' => 'Jenis'],
                                'amount' => ['label' => 'Nominal', 'align' => 'end'],
                                'balance' => ['label' => 'Saldo', 'align' => 'end'],
                                'date' => ['label' => 'Tanggal', 'align' => 'end'],
                            ])
                            ->rows(
                                $movements->map(fn ($movement) => [
                                    'description' => '<span class="text-truncate d-block">'.e($movement->description ?? '—').'</span>',
                                    'type' => $__status($movement->type, [
                                        'credit' => ['Masuk', 'success'],
                                        'debit' => ['Keluar', 'danger'],
                                    ]),
                                    'amount' => $movement->type === 'credit'
                                        ? '<span class="text-success fw-medium">+'.e(Currency::format($movement->amount)).'</span>'
                                        : '<span class="text-danger fw-medium">-'.e(Currency::format($movement->amount)).'</span>',
                                    'balance' => e(Currency::format($movement->balance_after)),
                                    'date' => '<span class="text-secondary small">'.e(\Carbon\Carbon::parse($movement->created_at)->format('d/m/Y H:i')).'</span>',
                                ])->all()
                            )
                            ->empty('Belum ada mutasi dompet.')
                    </x-slot:table>
                </x-admin.table>
            </x-admin.card>
        </div>

        <div class="col-12 col-xl-4">
            <x-admin.card title="Ajukan pencairan" icon="wallet-2">
                <x-admin.alert type="info">
                    Rekening tujuan pada halaman ini hanya ditampilkan empat digit terakhir untuk melindungi data Anda.
                </x-admin.alert>

                <form method="POST" action="{{ route('vendor.wallet.withdraw') }}">
                    @csrf

                    <x-admin.form-field
                        name="amount"
                        label="Nominal"
                        type="number"
                        :min="10000"
                        :step="1"
                        required
                        :prefix="Currency::config()['symbol']"
                        help="Minimal pencairan Rp10.000."
                    />

                    <div class="row g-2">
                        <div class="col-12">
                            <x-admin.form-field name="bank_name" label="Bank" required />
                        </div>
                        <div class="col-12">
                            <x-admin.form-field name="bank_account_name" label="Atas nama" required />
                        </div>
                        <div class="col-12">
                            <x-admin.form-field
                                name="bank_account_number"
                                label="Nomor rekening"
                                required
                                help="Tersimpan: {{ $bank_account ?: 'belum diisi' }}"
                                placeholder="{{ $bank_account }}"
                            />
                        </div>
                        <div class="col-12">
                            <x-admin.form-field name="note" label="Catatan" type="textarea" :rows="2" />
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" data-confirm="Ajukan pencairan dana sekarang?">
                        <x-admin.icon name="cash-coin" :size="16" class="me-1" />
                        <span>Ajukan pencairan</span>
                    </button>
                </form>
            </x-admin.card>
        </div>
    </div>
@endsection
