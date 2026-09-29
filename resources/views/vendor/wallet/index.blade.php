@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Dompet')
@section('subtitle', 'Saldo, mutasi, dan pencairan dana')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Dompet'],
])

@section('actions')
    <a href="{{ route('vendor.finance.payouts') }}" class="btn btn-primary">
        <x-admin.icon name="cash-coin" :size="16" class="me-1" />
        <span>Ajukan pencairan</span>
    </a>
@endsection

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl">
            <x-admin.stat label="Saldo tersedia" :value="($wallet?->balance ?? 0)" icon="wallet" color="success" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Saldo tertahan" :value="($wallet?->pending_balance ?? 0)" icon="clock" color="warning" hint="Sedang dalam proses pencairan" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Total produk" :value="app('request')->user()?->shop?->products()->count() ?? 0" icon="package" color="primary" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Rekening tujuan" :value="$savedBank['bank_account_number'] ?: '—'" icon="credit-card" color="secondary" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-admin.card title="Mutasi dompet" icon="activity" :padding="false">
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
                                $transactions->getCollection()->map(fn ($transaction) => [
                                    'description' => '<span class="text-truncate d-block">'.e($transaction->description ?? '—').'</span>',
                                    'type' => $__status($transaction->type, [
                                        'credit' => ['Masuk', 'success'],
                                        'debit' => ['Keluar', 'danger'],
                                    ]),
                                    'amount' => $transaction->type === 'credit'
                                        ? '<span class="text-success fw-medium">+'.e(Currency::format($transaction->amount)).'</span>'
                                        : '<span class="text-danger fw-medium">-'.e(Currency::format($transaction->amount)).'</span>',
                                    'balance' => e(Currency::format($transaction->balance_after)),
                                    'date' => '<span class="text-secondary small">'.e($transaction->created_at->format('d/m/Y H:i')).'</span>',
                                ])->all()
                            )
                            ->empty('Belum ada mutasi dompet.')
                    </x-slot:table>
                </x-admin.table>
            </x-admin.card>

            <x-admin.card title="Riwayat pencairan" icon="cash-coin" class="mt-3" :padding="false">
                <x-admin.table dense>
                    <x-slot:table>
                        \App\Support\TableBuilder::make()
                            ->columns([
                                'amount' => ['label' => 'Nominal'],
                                'bank' => ['label' => 'Rekening'],
                                'status' => ['label' => 'Status'],
                                'date' => ['label' => 'Diajukan', 'align' => 'end'],
                            ])
                            ->rows(
                                $withdrawRequests->getCollection()->map(fn ($request) => [
                                    'amount' => '<span class="fw-medium">'.e(Currency::format($request->amount)).'</span>',
                                    'bank' => '<span class="text-secondary small font-monospace">'.e($__maskAccount($request->bank_account_number)).'</span>',
                                    'status' => $__status($request->status),
                                    'date' => '<span class="text-secondary small">'.e($request->created_at->format('d/m/Y')).'</span>',
                                ])->all()
                            )
                            ->empty('Belum ada permintaan pencairan.')
                    </x-slot:table>
                </x-admin.table>
            </x-admin.card>
        </div>

        <div class="col-12 col-xl-4">
            <x-admin.card title="Ajukan pencairan" icon="wallet-2">
                <x-admin.alert type="info">
                    Nomor rekening tujuan hanya ditampilkan empat digit terakhir. Data lengkap disimpan
                    di server dan tidak pernah ditampilkan kembali.
                </x-admin.alert>

                <form method="POST" action="{{ route('vendor.wallet.withdraw') }}">
                    @csrf

                    <x-admin.form-field
                        name="amount"
                        label="Nominal"
                        type="number"
                        :min="$minimum->toFloat()"
                        :step="1"
                        required
                        :prefix="Currency::config()['symbol']"
                        help="Minimal pencairan {{ Currency::format($minimum->toFloat()) }}."
                    />

                    <x-admin.form-field name="bank_name" label="Bank" required :value="$savedBank['bank_name']" />
                    <x-admin.form-field name="bank_account_name" label="Atas nama" required :value="$savedBank['bank_account_name']" />
                    <x-admin.form-field
                        name="bank_account_number"
                        label="Nomor rekening"
                        required
                        :value="$savedBank['bank_account_number']"
                        help="Tersimpan: {{ $savedBank['bank_account_number'] ?: 'belum diisi' }}"
                    />
                    <x-admin.form-field name="note" label="Catatan" type="textarea" :rows="2" />

                    <button type="submit" class="btn btn-primary w-100" data-confirm="Ajukan pencairan dana sekarang?">
                        <x-admin.icon name="cash-coin" :size="16" class="me-1" />
                        <span>Ajukan pencairan</span>
                    </button>
                </form>
            </x-admin.card>

            <x-admin.card title="Rincian rekening" icon="credit-card" class="mt-3">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-secondary fw-normal">Bank</dt>
                    <dd class="col-7 text-end">{{ $savedBank['bank_name'] ?: '—' }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Atas nama</dt>
                    <dd class="col-7 text-end text-truncate">{{ $savedBank['bank_account_name'] ?: '—' }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Nomor</dt>
                    <dd class="col-7 text-end font-monospace">{{ $savedBank['bank_account_number'] ?: '—' }}</dd>
                </dl>
                <a href="{{ route('vendor.settings.index') }}" class="btn btn-outline-secondary w-100 mt-3">
                    <x-admin.icon name="edit" :size="16" class="me-1" />
                    <span>Perbarui rekening</span>
                </a>
            </x-admin.card>
        </div>
    </div>
@endsection
