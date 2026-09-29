@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Komisi platform')
@section('subtitle', $rate)

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Keuangan', 'href' => route('vendor.finance.payouts')],
    ['label' => 'Komisi'],
])

@section('content')
    @include('admin.partials.date-range')

    <x-admin.tabs class="mb-3" :tabs="[
        ['label' => 'Pendapatan', 'href' => route('vendor.finance.revenue'), 'icon' => 'wallet'],
        ['label' => 'Komisi', 'href' => route('vendor.finance.commission'), 'active' => true, 'icon' => 'percent'],
        ['label' => 'Pencairan', 'href' => route('vendor.finance.payouts'), 'icon' => 'cash-coin'],
    ]" />

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl">
            <x-admin.stat label="Komisi periode ini" :value="$total->toFloat()" icon="percent" color="danger" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Nilai transaksi bruto" :value="$gross->toFloat()" icon="wallet" color="primary" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Bagian Anda" :value="$vendor_share->toFloat()" icon="cash-coin" color="success" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Komisi efektif" :value="number_format($effective, 2, ',', '.').' %'" icon="chart-pie" color="info" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-admin.chart
                id="vendor-commission-monthly"
                title="Komisi 12 bulan terakhir"
                :labels="collect($monthly)->pluck('label')->all()"
                :data="[
                    ['label' => 'Komisi platform', 'data' => collect($monthly)->pluck('commission')->map(fn ($value) => (float) $value)->all()],
                    ['label' => 'Dana untuk Anda', 'data' => collect($monthly)->pluck('payout')->map(fn ($value) => (float) $value)->all()],
                ]"
                :height="300"
            />

            <x-admin.card title="Transaksi terbaru" icon="receipt" class="mt-3" :padding="false">
                <x-admin.table dense>
                    <x-slot:table>
                        \App\Support\TableBuilder::make()
                            ->columns([
                                'reference' => ['label' => 'Referensi'],
                                'order' => ['label' => 'Pesanan'],
                                'amount' => ['label' => 'Bruto', 'align' => 'end'],
                                'commission' => ['label' => 'Komisi', 'align' => 'end'],
                                'vendor' => ['label' => 'Untuk Anda', 'align' => 'end'],
                                'status' => ['label' => 'Status'],
                                'date' => ['label' => 'Tanggal', 'align' => 'end'],
                            ])
                            ->rows(
                                $transactions->getCollection()->map(fn ($transaction) => [
                                    'reference' => '<span class="font-monospace small">'.e($transaction->reference ?? '—').'</span>',
                                    'order' => $transaction->order
                                        ? '<a href="'.route('vendor.orders.show', $transaction->order).'" class="fw-medium">'.e($transaction->order->order_number).'</a>'
                                        : '<span class="text-secondary">—</span>',
                                    'amount' => e(Currency::format($transaction->amount)),
                                    'commission' => '<span class="text-danger fw-medium">'.e(Currency::format($transaction->admin_commission)).'</span>',
                                    'vendor' => '<span class="text-success fw-medium">'.e(Currency::format($transaction->vendor_amount)).'</span>',
                                    'status' => $__status($transaction->status),
                                    'date' => '<span class="text-secondary small">'.e(\Carbon\Carbon::parse($transaction->created_at)->format('d/m/Y')).'</span>',
                                ])->all()
                            )
                            ->empty('Belum ada transaksi pada periode ini.')
                    </x-slot:table>
                </x-admin.table>

                <x-admin.pagination :paginator="$transactions" class="mt-3" />
            </x-admin.card>
        </div>

        <div class="col-12 col-xl-4">
            <x-admin.card title="Paket langganan" icon="layers">
                <p class="text-secondary small">
                    Tingkat komisi mengikuti paket langganan toko. Naik paket untuk menurunkan komisi dan menambah kuota.
                </p>
                <a href="{{ route('vendor.subscription.index') }}" class="btn btn-primary w-100">
                    <x-admin.icon name="layers" :size="16" class="me-1" />
                    <span>Kelola paket</span>
                </a>
            </x-admin.card>
        </div>
    </div>
@endsection
