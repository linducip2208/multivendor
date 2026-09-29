@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Laporan transaksi')
@section('subtitle', 'Dana untuk Anda '.Currency::format($totalSuccess->toFloat()))

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Laporan', 'href' => route('vendor.report.products')],
    ['label' => 'Transaksi'],
])

@section('content')
    <x-admin.tabs class="mb-3" :tabs="[
        ['label' => 'Produk', 'href' => route('vendor.report.products'), 'icon' => 'package'],
        ['label' => 'Pesanan', 'href' => route('vendor.report.orders'), 'icon' => 'shopping-cart'],
        ['label' => 'Transaksi', 'href' => route('vendor.report.transactions'), 'active' => true, 'icon' => 'receipt'],
    ]" />

    <x-admin.filters
        :action="route('vendor.report.transactions')"
        :filters="[['name' => 'status', 'label' => 'Status', 'type' => 'select', 'value' => $status, 'options' => [
            '' => 'Semua',
            'success' => 'Berhasil',
            'pending' => 'Menunggu',
            'failed' => 'Gagal',
            'refunded' => 'Dikembalikan',
        ]]]"
    />

    <x-admin.card :padding="false">
        <x-admin.table dense>
            <x-slot:table>
                \App\Support\TableBuilder::make()
                    ->columns([
                        'reference' => ['label' => 'Referensi'],
                        'order' => ['label' => 'Pesanan'],
                        'method' => ['label' => 'Metode'],
                        'amount' => ['label' => 'Bruto', 'align' => 'end'],
                        'commission' => ['label' => 'Komisi', 'align' => 'end'],
                        'vendor' => ['label' => 'Untuk Anda', 'align' => 'end'],
                        'status' => ['label' => 'Status'],
                        'date' => ['label' => 'Tanggal', 'align' => 'end'],
                    ])
                    ->rows(
                        $transactions->map(fn ($transaction) => [
                            'reference' => '<span class="font-monospace small">'.e($transaction->reference ?? '—').'</span>',
                            'order' => $transaction->order
                                ? '<a href="'.route('vendor.orders.show', $transaction->order).'" class="fw-medium">'.e($transaction->order->order_number).'</a>'
                                : '<span class="text-secondary">—</span>',
                            'method' => '<span class="text-secondary small text-uppercase">'.e($transaction->payment_method ?? '—').'</span>',
                            'amount' => e(Currency::format($transaction->amount)),
                            'commission' => '<span class="text-danger">'.e(Currency::format($transaction->admin_commission)).'</span>',
                            'vendor' => '<span class="text-success fw-medium">'.e(Currency::format($transaction->vendor_amount)).'</span>',
                            'status' => $__status($transaction->status),
                            'date' => '<span class="text-secondary small">'.e(\Carbon\Carbon::parse($transaction->created_at)->format('d/m/Y')).'</span>',
                        ])->all()
                    )
                    ->empty('Belum ada transaksi.')
            </x-slot:table>
        </x-admin.table>
    </x-admin.card>

    <x-admin.pagination :paginator="$transactions" class="mt-3" />
@endsection
