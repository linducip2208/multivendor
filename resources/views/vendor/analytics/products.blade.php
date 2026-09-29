@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Analitik produk')
@section('subtitle', $range->from->format('d M Y').' s/d '.$range->to->format('d M Y'))

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Analitik', 'href' => route('vendor.analytics.index')],
    ['label' => 'Produk'],
])

@section('content')
    @include('admin.partials.date-range')

    <x-admin.tabs class="mb-3" :tabs="[
        ['label' => 'Ringkasan', 'href' => route('vendor.analytics.index', $range->toArray()), 'icon' => 'dashboard'],
        ['label' => 'Penjualan', 'href' => route('vendor.analytics.sales', $range->toArray()), 'icon' => 'chart-line'],
        ['label' => 'Pelanggan', 'href' => route('vendor.analytics.customers', $range->toArray()), 'icon' => 'users'],
        ['label' => 'Produk', 'href' => route('vendor.analytics.products', $range->toArray()), 'active' => true, 'icon' => 'package'],
    ]" />

    <div class="row g-3 mb-3">
        <div class="col-4 col-xl">
            <x-admin.stat label="Produk aktif" :value="$catalogue" icon="package" color="primary" />
        </div>
        <div class="col-4 col-xl">
            <x-admin.stat label="Produk terjual" :value="count($sold)" icon="shopping-cart" color="success" />
        </div>
        <div class="col-4 col-xl">
            <x-admin.stat label="Stok menipis" :value="$low_stock" icon="alert-triangle" color="warning" :href="route('vendor.products.low-stock')" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-admin.card title="Produk terlaris" icon="award" :padding="false">
                <x-admin.table dense>
                    <x-slot:table>
                        \App\Support\TableBuilder::make()
                            ->columns([
                                'product' => ['label' => 'Produk', 'width' => '38%'],
                                'units' => ['label' => 'Unit', 'align' => 'end'],
                                'buyers' => ['label' => 'Pembeli', 'align' => 'end'],
                                'stock' => ['label' => 'Sisa stok', 'align' => 'end'],
                                'revenue' => ['label' => 'Pendapatan', 'align' => 'end'],
                            ])
                            ->rows(
                                collect($sold)->map(fn (array $row) => [
                                    'product' => '<span class="fw-medium d-block text-truncate">'.e($row['name']).'</span>',
                                    'units' => e(Currency::number($row['units'])),
                                    'buyers' => e(Currency::number($row['buyers'])),
                                    'stock' => '<span class="badge bg-'.($row['stock'] <= 0 ? 'danger' : ($row['stock'] <= 5 ? 'warning' : 'success')).'-lt text-'.($row['stock'] <= 0 ? 'danger' : ($row['stock'] <= 5 ? 'warning' : 'success')).'">'.e(Currency::number($row['stock'])).'</span>',
                                    'revenue' => '<span class="fw-medium">'.e(Currency::format($row['revenue']->toFloat())).'</span>',
                                ])->all()
                            )
                            ->empty('Tidak ada produk terjual pada periode ini.')
                    </x-slot:table>
                </x-admin.table>
            </x-admin.card>
        </div>

        <div class="col-12 col-xl-4">
            <x-admin.card title="Belum terjual" icon="package" :padding="false">
                <x-slot:actions>
                    <a href="{{ route('vendor.products.index') }}" class="btn btn-sm btn-ghost-light">Kelola</a>
                </x-slot:actions>

                <x-admin.table dense>
                    <x-slot:table>
                        \App\Support\TableBuilder::make()
                            ->columns([
                                'product' => ['label' => 'Produk'],
                                'stock' => ['label' => 'Stok', 'align' => 'end'],
                            ])
                            ->rows(
                                collect($unsold)->map(fn (array $row) => [
                                    'product' => '<span class="text-truncate d-block">'.e($row['name']).'</span>',
                                    'stock' => e(Currency::number($row['stock'])),
                                ])->all()
                            )
                            ->empty('Semua produk pernah terjual pada periode ini.')
                    </x-slot:table>
                </x-admin.table>
            </x-admin.card>
        </div>
    </div>
@endsection
