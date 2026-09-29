@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Permintaan restock')
@section('subtitle', 'Pelanggan yang menunggu barang tersedia kembali')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Restock'],
])

@section('actions')
    <a href="{{ route('vendor.products.low-stock') }}" class="btn btn-outline-secondary">
        <x-admin.icon name="alert-triangle" :size="16" class="me-1" />
        <span>Stok menipis</span>
    </a>
@endsection

@section('content')
    <x-admin.alert type="info">
        Permintaan restok dibuat otomatis saat pelanggan menandai minat pada produk yang kehabisan stok.
        Memberi tahu pelanggan tidak mengubah stok — lakukan restock melalui halaman Inventori.
    </x-admin.alert>

    <x-admin.card :padding="false">
        <x-admin.table>
            <x-slot:table>
                \App\Support\TableBuilder::make()
                    ->columns([
                        'product' => ['label' => 'Produk', 'width' => '30%'],
                        'sku' => ['label' => 'SKU'],
                        'customer' => ['label' => 'Pelanggan', 'width' => '26%'],
                        'stock' => ['label' => 'Stok kini', 'align' => 'end'],
                        'status' => ['label' => 'Status'],
                        'date' => ['label' => 'Diminta', 'align' => 'end'],
                        'actions' => ['label' => '', 'align' => 'end', 'width' => '160px'],
                    ])
                    ->rows(
                        $requests->map(fn ($request) => [
                            'product' => '<a href="'.route('vendor.products.edit', $request->product_id).'" class="fw-medium d-block text-reset text-truncate">'.e($request->product_name).'</a>',
                            'sku' => e($request->product_sku ?: '—'),
                            'customer' => '<span class="d-block text-truncate fw-medium">'.e($request->customer_name ?: '—').'</span><span class="text-secondary small text-truncate d-block">'.e($request->customer_email ?: '—').'</span>',
                            'stock' => (int) $request->current_stock > 0
                                ? '<span class="badge bg-success-lt text-success rounded-pill">'.e(Currency::number($request->current_stock)).'</span>'
                                : '<span class="badge bg-danger-lt text-danger rounded-pill">Habis</span>',
                            'status' => $__status($request->status),
                            'date' => '<span class="text-secondary small">'.e(\Carbon\Carbon::parse($request->created_at)->format('d/m/Y H:i')).'</span>',
                            'actions' => $request->status === 'notified'
                                ? '<span class="text-secondary small">Sudah diberi tahu</span>'
                                : '<form method="POST" action="'.route('vendor.restock.notify').'">'
                                    .csrf()
                                    .'<input type="hidden" name="id" value="'.(int) $request->id.'">'
                                    .'<button type="submit" class="btn btn-sm btn-outline-primary">Beri tahu</button></form>',
                        ])->all()
                    )
                    ->empty('Belum ada permintaan restock.')
            </x-slot:table>
        </x-admin.table>
    </x-admin.card>
@endsection
