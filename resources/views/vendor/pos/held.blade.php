@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Hold order')
@section('subtitle', 'Pesanan POS yang ditahan untuk dilayani nanti')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'POS', 'href' => route('vendor.pos.index')],
    ['label' => 'Hold order'],
])

@section('actions')
    <a href="{{ route('vendor.pos.index') }}" class="btn btn-primary">
        <x-admin.icon name="plus" :size="16" class="me-1" />
        <span>Kasir baru</span>
    </a>
@endsection

@section('content')
    <x-admin.alert type="info">
        Hold order tidak mengurangi stok. Stok baru dipotong ketika hold order dilanjutkan menjadi transaksi lunas,
        dan validate stok tetap berjalan di titik itu.
    </x-admin.alert>

    <x-admin.card :padding="false">
        <x-admin.table>
            <x-slot:table>
                \App\Support\TableBuilder::make()
                    ->columns([
                        'order' => ['label' => 'Nomor', 'width' => '210px'],
                        'customer' => ['label' => 'Pelanggan'],
                        'items' => ['label' => 'Item', 'align' => 'end'],
                        'total' => ['label' => 'Total', 'align' => 'end'],
                        'date' => ['label' => 'Dibuat', 'align' => 'end'],
                        'actions' => ['label' => '', 'align' => 'end', 'width' => '260px'],
                    ])
                    ->rows(
                        $orders->map(fn ($order) => [
                            'order' => '<a href="'.route('vendor.orders.show', $order).'" class="font-monospace fw-medium">'.e($order->order_number).'</a>',
                            'customer' => '<span class="text-truncate d-block">'.e(str_replace('POS: ', '', (string) $order->note) ?: 'Pelanggan walk-in').'</span>',
                            'items' => e(Currency::number($order->items->sum('quantity'))),
                            'total' => '<span class="fw-medium text-nowrap">'.e(Currency::format($order->total)).'</span>',
                            'date' => '<span class="text-secondary small">'.e($order->created_at->format('d/m/Y H:i')).'</span>',
                            'actions' => '<div class="d-flex gap-1 justify-content-end">'
                                .'<form method="POST" action="'.route('vendor.pos.resume', $order).'" class="d-inline">'
                                .csrf().'<button type="submit" class="btn btn-sm btn-success">Lanjutkan</button></form>'
                                .'<a href="'.route('vendor.pos.print', $order).'" class="btn btn-sm btn-ghost-light" target="_blank" rel="noopener">Cetak</a>'
                                .'<form method="POST" action="'.route('vendor.pos.cancel-hold', $order).'" class="d-inline" data-confirm="Batalkan hold order '.e($order->order_number).'?">'
                                .csrf().'<button type="submit" class="btn btn-sm btn-ghost-danger">Batal</button></form>'
                                .'</div>',
                        ])->all()
                    )
                    ->empty('Tidak ada hold order.')
            </x-slot:table>
        </x-admin.table>
    </x-admin.card>

    <x-admin.pagination :paginator="$orders" class="mt-3" />
@endsection
