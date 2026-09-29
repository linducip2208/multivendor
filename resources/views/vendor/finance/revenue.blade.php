@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Pendapatan')
@section('subtitle', $range->from->format('d M Y').' s/d '.$range->to->format('d M Y'))

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Keuangan', 'href' => route('vendor.finance.payouts')],
    ['label' => 'Pendapatan'],
])

@section('content')
    @include('admin.partials.date-range')

    <x-admin.tabs class="mb-3" :tabs="[
        ['label' => 'Pendapatan', 'href' => route('vendor.finance.revenue'), 'active' => true, 'icon' => 'wallet'],
        ['label' => 'Komisi', 'href' => route('vendor.finance.commission'), 'icon' => 'percent'],
        ['label' => 'Pencairan', 'href' => route('vendor.finance.payouts'), 'icon' => 'cash-coin'],
    ]" />

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl">
            <x-admin.stat label="Kotor" :value="$gross->toFloat()" icon="wallet" color="primary" :trend="$growth['value']" trend-label="vs periode lalu" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Bersih" :value="$net->toFloat()" icon="cash-coin" color="success" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Pesanan" :value="$orders" icon="shopping-cart" color="info" :trend="$order_growth['value']" trend-label="vs periode lalu" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Nilai rata-rata" :value="$aov->toFloat()" icon="chart-line" color="warning" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Pajak" :value="$tax->toFloat()" icon="receipt" color="secondary" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Ongkir" :value="$shipping->toFloat()" icon="truck" color="secondary" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Diskon" :value="$discount->toFloat()" icon="tag" color="danger" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Komisi platform" :value="$commission->toFloat()" icon="percent" color="dark" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-admin.chart
                id="vendor-revenue-series"
                title="Pendapatan harian"
                :labels="collect($series)->pluck('label')->all()"
                :data="[['label' => 'Pendapatan', 'data' => collect($series)->pluck('revenue')->map(fn ($value) => (float) $value)->all()]]"
                :height="320"
                filled
            />
        </div>

        <div class="col-12 col-xl-4">
            <x-admin.card title="Sumber pendapatan" icon="target" :padding="false">
                <x-admin.table dense>
                    <x-slot:table>
                        \App\Support\TableBuilder::make()
                            ->columns([
                                'source' => ['label' => 'Sumber'],
                                'orders' => ['label' => 'Pesanan', 'align' => 'end'],
                                'revenue' => ['label' => 'Nilai', 'align' => 'end'],
                            ])
                            ->rows(
                                collect($sources)->map(fn (array $row) => [
                                    'source' => '<span class="text-truncate d-block fw-medium">'.e($row['label']).'</span>',
                                    'orders' => e(Currency::number($row['orders'])),
                                    'revenue' => '<span class="fw-medium">'.e(Currency::format($row['revenue']->toFloat())).'</span>',
                                ])->all()
                            )
                            ->empty('Tidak ada penjualan pada periode ini.')
                    </x-slot:table>
                </x-admin.table>
            </x-admin.card>
        </div>
    </div>
@endsection
