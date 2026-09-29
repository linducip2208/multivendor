@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Laporan produk')
@section('subtitle', 'Unit terjual dan pendapatan per produk')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Laporan'],
    ['label' => 'Produk'],
])

@section('content')
    <x-admin.tabs class="mb-3" :tabs="[
        ['label' => 'Produk', 'href' => route('vendor.report.products'), 'active' => true, 'icon' => 'package'],
        ['label' => 'Pesanan', 'href' => route('vendor.report.orders'), 'icon' => 'shopping-cart'],
        ['label' => 'Transaksi', 'href' => route('vendor.report.transactions'), 'icon' => 'receipt'],
    ]" />

    <x-admin.filters
        :action="route('vendor.report.products')"
        :filters="[['name' => 'search', 'label' => 'Cari produk', 'placeholder' => 'Nama produk']]"
    />

    <x-admin.card :padding="false">
        <x-admin.table dense>
            <x-slot:table>
                \App\Support\TableBuilder::make()
                    ->columns([
                        'product' => ['label' => 'Produk', 'width' => '38%'],
                        'sku' => ['label' => 'SKU'],
                        'sold' => ['label' => 'Terjual', 'align' => 'end'],
                        'stock' => ['label' => 'Sisa stok', 'align' => 'end'],
                        'revenue' => ['label' => 'Pendapatan', 'align' => 'end'],
                    ])
                    ->rows(
                        $products->map(fn ($product) => [
                            'product' => '<a href="'.route('vendor.products.show', $product).'" class="fw-medium d-block text-reset text-truncate">'.e($product->name).'</a>',
                            'sku' => e($product->sku ?: '—'),
                            'sold' => e(Currency::number($product->sold ?? 0)),
                            'stock' => e(Currency::number($product->current_stock)),
                            'revenue' => '<span class="fw-medium text-nowrap">'.e(Currency::format($product->revenue ?? 0)).'</span>',
                        ])->all()
                    )
                    ->empty('Tidak ada produk yang cocok.')
            </x-slot:table>
        </x-admin.table>
    </x-admin.card>

    <x-admin.pagination :paginator="$products" class="mt-3" />
@endsection
