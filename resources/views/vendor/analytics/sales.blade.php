@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Analitik penjualan')
@section('subtitle', $range->from->format('d M Y').' s/d '.$range->to->format('d M Y'))

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Analitik', 'href' => route('vendor.analytics.index')],
    ['label' => 'Penjualan'],
])

@section('content')
    @include('admin.partials.date-range')

    <x-admin.tabs class="mb-3" :tabs="[
        ['label' => 'Ringkasan', 'href' => route('vendor.analytics.index', $range->toArray()), 'icon' => 'dashboard'],
        ['label' => 'Penjualan', 'href' => route('vendor.analytics.sales', $range->toArray()), 'active' => true, 'icon' => 'chart-line'],
        ['label' => 'Pelanggan', 'href' => route('vendor.analytics.customers', $range->toArray()), 'icon' => 'users'],
        ['label' => 'Produk', 'href' => route('vendor.analytics.products', $range->toArray()), 'icon' => 'package'],
    ]" />

    <div class="row g-3 mb-3">
        <div class="col-4 col-xl">
            <x-admin.stat label="Kotor" :value="$summary['gross']->toFloat()" icon="wallet" color="primary" />
        </div>
        <div class="col-4 col-xl">
            <x-admin.stat label="Pajak terkumpul" :value="$summary['tax']->toFloat()" icon="receipt" color="info" />
        </div>
        <div class="col-4 col-xl">
            <x-admin.stat label="Pesanan lunas" :value="$summary['orders']" icon="shopping-cart" color="success" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-admin.card title="Pesanan dalam periode" icon="list" :padding="false">
                <x-admin.table dense>
                    <x-slot:table>
                        \App\Support\TableBuilder::make()
                            ->columns([
                                'order' => ['label' => 'Pesanan'],
                                'customer' => ['label' => 'Pelanggan'],
                                'status' => ['label' => 'Status'],
                                'payment' => ['label' => 'Pembayaran'],
                                'total' => ['label' => 'Total', 'align' => 'end'],
                                'date' => ['label' => 'Tanggal', 'align' => 'end'],
                            ])
                            ->rows(
                                $rows->map(fn (array $row) => [
                                    'order' => '<a href="'.route('vendor.orders.show', $row['order']).'" class="fw-medium">'.e($row['order']->order_number).'</a>',
                                    'customer' => '<span class="text-truncate d-block">'.e($row['customer']).'</span>',
                                    'status' => $__orderStatus($row['order']->order_status),
                                    'payment' => $__paymentStatus($row['order']->payment_status),
                                    'total' => '<span class="fw-medium">'.e(Currency::format($row['total']->toFloat())).'</span>',
                                    'date' => '<span class="text-secondary small">'.e($row['order']->created_at->format('d/m/Y')).'</span>',
                                ])->all()
                            )
                            ->empty('Tidak ada pesanan lunas pada periode ini.')
                    </x-slot:table>
                </x-admin.table>
            </x-admin.card>
        </div>

        <div class="col-12 col-xl-4">
            <x-admin.card title="Hari terbaik" icon="award" :padding="false">
                <x-admin.table dense>
                    <x-slot:table>
                        \App\Support\TableBuilder::make()
                            ->columns([
                                'day' => ['label' => 'Tanggal'],
                                'orders' => ['label' => 'Pesanan', 'align' => 'end'],
                                'revenue' => ['label' => 'Pendapatan', 'align' => 'end'],
                            ])
                            ->rows(
                                collect($top_days)->map(fn (array $row) => [
                                    'day' => '<span class="fw-medium">'.e(\Carbon\Carbon::parse($row['day'])->format('d M Y')).'</span>',
                                    'orders' => e(Currency::number($row['orders'])),
                                    'revenue' => '<span class="fw-medium">'.e(Currency::format($row['revenue']->toFloat())).'</span>',
                                ])->all()
                            )
                            ->empty('Belum ada data penjualan.')
                    </x-slot:table>
                </x-admin.table>
            </x-admin.card>
        </div>
    </div>
@endsection
