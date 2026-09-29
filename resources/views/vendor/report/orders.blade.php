@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Laporan pesanan')
@section('subtitle', 'Nilai '.Currency::format($totalRevenue->toFloat()))

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Laporan', 'href' => route('vendor.report.products')],
    ['label' => 'Pesanan'],
])

@section('content')
    <x-admin.tabs class="mb-3" :tabs="[
        ['label' => 'Produk', 'href' => route('vendor.report.products'), 'icon' => 'package'],
        ['label' => 'Pesanan', 'href' => route('vendor.report.orders'), 'active' => true, 'icon' => 'shopping-cart'],
        ['label' => 'Transaksi', 'href' => route('vendor.report.transactions'), 'icon' => 'receipt'],
    ]" />

    <x-admin.filters
        :action="route('vendor.report.orders')"
        :filters="[
            ['name' => 'from', 'label' => 'Dari', 'type' => 'date', 'value' => request('from')],
            ['name' => 'to', 'label' => 'Sampai', 'type' => 'date', 'value' => request('to')],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'value' => $status, 'options' => [
                '' => 'Semua',
                'pending' => 'Menunggu',
                'paid' => 'Sudah dibayar',
                'confirmed' => 'Dikonfirmasi',
                'processing' => 'Diproses',
                'packed' => 'Dikemas',
                'shipped' => 'Dikirim',
                'delivered' => 'Diterima',
                'completed' => 'Selesai',
                'canceled' => 'Dibatalkan',
                'refunded' => 'Dikembalikan',
            ]],
        ]"
    />

    <x-admin.card :padding="false">
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
                        $orders->map(fn ($order) => [
                            'order' => '<a href="'.route('vendor.orders.show', $order).'" class="fw-medium">'.e($order->order_number).'</a>',
                            'customer' => '<span class="text-truncate d-block">'.e($order->customer?->name ?? 'Pelanggan').'</span>',
                            'status' => $__orderStatus($order->order_status),
                            'payment' => $__paymentStatus($order->payment_status),
                            'total' => '<span class="fw-medium text-nowrap">'.e(Currency::format($order->total)).'</span>',
                            'date' => '<span class="text-secondary small">'.e($order->created_at->format('d/m/Y')).'</span>',
                        ])->all()
                    )
                    ->empty('Tidak ada pesanan pada rentang ini.')
            </x-slot:table>
        </x-admin.table>
    </x-admin.card>

    <x-admin.pagination :paginator="$orders" class="mt-3" />
@endsection
