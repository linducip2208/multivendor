@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Stok menipis')
@section('subtitle', 'Produk di bawah ambang stok atau sudah habis')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Produk', 'href' => route('vendor.products.index')],
    ['label' => 'Stok menipis'],
])

@section('actions')
    <a href="{{ route('vendor.inventory.index') }}" class="btn btn-outline-secondary">
        <x-admin.icon name="box" :size="16" class="me-1" />
        <span>Inventori</span>
    </a>
@endsection

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-4 col-xl">
            <x-admin.stat label="Produk" :value="$stats['total']" icon="package" color="primary" />
        </div>
        <div class="col-4 col-xl">
            <x-admin.stat label="Menipis" :value="$stats['low_stock']" icon="alert-triangle" color="warning" />
        </div>
        <div class="col-4 col-xl">
            <x-admin.stat label="Habis" :value="$stats['out_of_stock']" icon="alert-circle" color="danger" />
        </div>
    </div>

    <x-admin.filters
        :action="route('vendor.products.low-stock')"
        :filters="[['name' => 'search', 'label' => 'Cari produk', 'placeholder' => 'Nama atau SKU']]"
    />

    <x-admin.card :padding="false">
        <x-admin.table>
            <x-slot:table>
                \App\Support\TableBuilder::make()
                    ->columns([
                        'product' => ['label' => 'Produk', 'width' => '38%'],
                        'sku' => ['label' => 'SKU'],
                        'stock' => ['label' => 'Stok', 'align' => 'end'],
                        'threshold' => ['label' => 'Ambang', 'align' => 'end'],
                        'status' => ['label' => 'Kondisi'],
                        'actions' => ['label' => '', 'align' => 'end', 'width' => '170px'],
                    ])
                    ->rows(
                        $products->map(fn ($product) => [
                            'product' => '<a href="'.route('vendor.products.edit', $product).'" class="fw-medium d-block text-reset text-truncate">'.e($product->name).'</a>',
                            'sku' => e($product->sku ?: '—'),
                            'stock' => '<span class="fw-medium">'.e(Currency::number($product->current_stock)).'</span>',
                            'threshold' => e(Currency::number($product->low_stock_threshold ?? 0)),
                            'status' => (int) $product->current_stock <= 0
                                ? '<span class="badge bg-danger-lt text-danger rounded-pill">Habis</span>'
                                : '<span class="badge bg-warning-lt text-warning rounded-pill">Menipis</span>',
                            'actions' => '<div class="d-flex gap-1 justify-content-end">'
                                .'<a href="'.route('vendor.products.edit', $product).'" class="btn btn-sm btn-ghost-light">Produk</a>'
                                .'<a href="'.route('vendor.inventory.index', ['search' => $product->sku ?: $product->name]).'" class="btn btn-sm btn-primary">Stok</a>'
                                .'</div>',
                        ])->all()
                    )
                    ->empty('Tidak ada produk dengan stok rendah.')
            </x-slot:table>
        </x-admin.table>
    </x-admin.card>

    <x-admin.pagination :paginator="$products" class="mt-3" />
@endsection
