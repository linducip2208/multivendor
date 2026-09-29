@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Analitik pelanggan')
@section('subtitle', $range->from->format('d M Y').' s/d '.$range->to->format('d M Y'))

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Analitik', 'href' => route('vendor.analytics.index')],
    ['label' => 'Pelanggan'],
])

@section('content')
    @include('admin.partials.date-range')

    <x-admin.tabs class="mb-3" :tabs="[
        ['label' => 'Ringkasan', 'href' => route('vendor.analytics.index', $range->toArray()), 'icon' => 'dashboard'],
        ['label' => 'Penjualan', 'href' => route('vendor.analytics.sales', $range->toArray()), 'icon' => 'chart-line'],
        ['label' => 'Pelanggan', 'href' => route('vendor.analytics.customers', $range->toArray()), 'active' => true, 'icon' => 'users'],
        ['label' => 'Produk', 'href' => route('vendor.analytics.products', $range->toArray()), 'icon' => 'package'],
    ]" />

    <div class="row g-3 mb-3">
        <div class="col-4 col-xl">
            <x-admin.stat label="Pelanggan uniques" :value="$total" icon="users" color="primary" />
        </div>
        <div class="col-4 col-xl">
            <x-admin.stat label="Tingkat pengulangan" :value="$repeat_rate.' %'" icon="refresh" color="success" />
        </div>
        <div class="col-4 col-xl">
            <x-admin.stat label="Belanja rata-rata" :value="$average_spend->toFloat()" icon="wallet" color="info" />
        </div>
    </div>

    <x-admin.card :padding="false">
        <x-admin.table dense>
            <x-slot:table>
                \App\Support\TableBuilder::make()
                    ->columns([
                        'customer' => ['label' => 'Pelanggan', 'width' => '40%'],
                        'orders' => ['label' => 'Pesanan', 'align' => 'end'],
                        'spend' => ['label' => 'Total belanja', 'align' => 'end'],
                        'last' => ['label' => 'Pesanan terakhir', 'align' => 'end'],
                    ])
                    ->rows(
                        collect($rows)->map(fn (array $row) => [
                            'customer' => '<a href="'.route('vendor.customers.show', $row['id']).'" class="fw-medium text-reset d-block text-truncate">'.e($row['name']).'</a>',
                            'orders' => e(Currency::number($row['orders'])),
                            'spend' => '<span class="fw-medium">'.e(Currency::format($row['spend']->toFloat())).'</span>',
                            'last' => '<span class="text-secondary small">'.e(\Carbon\Carbon::parse($row['last_order_at'])->format('d/m/Y')).'</span>',
                        ])->all()
                    )
                    ->empty('Tidak ada pelanggan yang memesan pada periode ini.')
            </x-slot:table>
        </x-admin.table>
    </x-admin.card>
@endsection
