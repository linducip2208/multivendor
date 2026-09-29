@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Analitik')
@section('subtitle', $range->from->format('d M Y').' s/d '.$range->to->format('d M Y'))

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Analitik'],
])

@section('actions')
    <a href="{{ route('vendor.analytics.sales', $range->toArray()) }}" class="btn btn-outline-secondary">
        <x-admin.icon name="list" :size="16" class="me-1" />
        <span>Pesanan</span>
    </a>
@endsection

@section('content')
    @include('admin.partials.date-range')

    <x-admin.tabs class="mb-3" :tabs="[
        ['label' => 'Ringkasan', 'href' => route('vendor.analytics.index', $range->toArray()), 'active' => true, 'icon' => 'dashboard'],
        ['label' => 'Penjualan', 'href' => route('vendor.analytics.sales', $range->toArray()), 'icon' => 'chart-line'],
        ['label' => 'Pelanggan', 'href' => route('vendor.analytics.customers', $range->toArray()), 'icon' => 'users'],
        ['label' => 'Produk', 'href' => route('vendor.analytics.products', $range->toArray()), 'icon' => 'package'],
    ]" />

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl">
            <x-admin.stat label="Pendapatan" :value="$revenue->toFloat()" icon="wallet" color="primary" :trend="$growth['value']" trend-label="vs periode lalu" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Pesanan" :value="$orders" icon="shopping-cart" color="info" :trend="$order_growth['value']" trend-label="vs periode lalu" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Nilai rata-rata" :value="$aov->toFloat()" icon="chart-line" color="success" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Pelanggan baru" :value="$new_customers" icon="user-plus" color="warning" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Pembatalan" :value="$cancellation_rate.' %'" icon="alert-triangle" color="danger" :hint="Currency::number($cancelled).' pesanan'" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Rating" :value="number_format($ratings, 2, ',', '.')" icon="star" color="danger" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-admin.chart
                id="vendor-analytics-trend"
                title="Pendapatan harian"
                :labels="collect($series)->pluck('label')->all()"
                :data="[['label' => 'Pendapatan', 'data' => collect($series)->pluck('revenue')->map(fn ($value) => (float) $value)->all()]]"
                :height="320"
                filled
            />
        </div>

        <div class="col-12 col-xl-4">
            <x-admin.card title="Komposisi pesanan" icon="chart-pie">
                <x-admin.chart
                    id="vendor-status-mix"
                    type="doughnut"
                    :labels="collect($status_mix)->pluck('label')->all()"
                    :data="[['label' => 'Pesanan', 'data' => collect($status_mix)->pluck('total')->all()]]"
                    :height="260"
                />
            </x-admin.card>
        </div>
    </div>

    @isset($compare)
        <x-admin.card title="Perbandingan periode" icon="chart-line" class="mt-3">
            <div class="row g-3 small">
                <div class="col-6 col-xl-3"><span class="text-secondary d-block">Pendapatan berjalan</span><strong>{{ Currency::format($compare['current']['gross']->toFloat()) }}</strong></div>
                <div class="col-6 col-xl-3"><span class="text-secondary d-block">Pendapatan sebelumnya</span><strong>{{ Currency::format($compare['previous']['gross']->toFloat()) }}</strong></div>
                <div class="col-6 col-xl-3"><span class="text-secondary d-block">Pesanan berjalan</span><strong>{{ $compare['current']['orders'] }}</strong></div>
                <div class="col-6 col-xl-3"><span class="text-secondary d-block">Pesanan sebelumnya</span><strong>{{ $compare['previous']['orders'] }}</strong></div>
            </div>
        </x-admin.card>
    @endisset

    @isset($funnel)
        <x-admin.card title="Funnel checkout" icon="filter" class="mt-3">
            <x-admin.table dense>
                <x-slot:table>
                    \App\Support\TableBuilder::make()
                        ->columns([
                            'step' => ['label' => 'Tahap'],
                            'total' => ['label' => 'Jumlah', 'align' => 'end'],
                            'drop' => ['label' => 'Drop-off ke tahap berikut', 'align' => 'end'],
                        ])
                        ->rows(
                            collect($funnel['steps'])->map(function ($step, $i) use ($funnel) {
                                $drop = $funnel['drop_off'][$i] ?? null;
                                return [
                                    'step' => e($step['label']),
                                    'total' => \App\Support\Currency::number($step['total']),
                                    'drop' => $drop ? e($drop['rate'].'% ke '.$drop['to']) : '—',
                                ];
                            })->all()
                        )
                        ->empty('Belum ada data funnel pada periode ini.')
                </x-slot:table>
            </x-admin.table>
        </x-admin.card>
    @endisset
@endsection
