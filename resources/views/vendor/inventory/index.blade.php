@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Inventori')
@section('subtitle', 'Stok fisik dan pergerakan barang')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Inventori'],
])

@section('actions')
    <a href="{{ route('vendor.inventory.movements') }}" class="btn btn-outline-secondary">
        <x-admin.icon name="history" :size="16" class="me-1" />
        <span>Pergerakan</span>
    </a>
    <a href="{{ route('vendor.restock.index') }}" class="btn btn-primary">
        <x-admin.icon name="bell" :size="16" class="me-1" />
        <span>Permintaan restock</span>
    </a>
@endsection

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl">
            <x-admin.stat label="Produk" :value="$stats['total']" icon="package" color="primary" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Unit tersedia" :value="$stats['units']" icon="box" color="info" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Stok menipis" :value="$stats['low_stock']" icon="alert-triangle" color="warning" :href="route('vendor.products.low-stock')" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Stok habis" :value="$stats['out_of_stock']" icon="alert-circle" color="danger" />
        </div>
        <div class="col-12 col-xl">
            <x-admin.stat label="Nilai katalog" :value="$stats['value']" icon="wallet" color="success" />
        </div>
    </div>

    <x-admin.filters
        :action="route('vendor.inventory.index')"
        :filters="[
            ['name' => 'search', 'label' => 'Cari produk', 'placeholder' => 'Nama, SKU, atau barcode'],
            ['name' => 'stock', 'label' => 'Status stok', 'type' => 'select', 'value' => $stock, 'options' => [
                '' => 'Semua',
                'in' => 'Aman',
                'low' => 'Menipis',
                'out' => 'Habis',
            ]],
        ]"
    />

    <x-admin.card :padding="false">
        <x-admin.table dense>
            <x-slot:table>
                \App\Support\TableBuilder::make()
                    ->columns([
                        'product' => ['label' => 'Produk', 'width' => '32%'],
                        'sku' => ['label' => 'SKU'],
                        'stock' => ['label' => 'Stok', 'align' => 'end'],
                        'threshold' => ['label' => 'Ambang', 'align' => 'end'],
                        'status' => ['label' => 'Kondisi'],
                        'actions' => ['label' => '', 'align' => 'end', 'width' => '150px'],
                    ])
                    ->rows(
                        $products->map(fn ($product) => [
                            'product' => '<a href="'.route('vendor.products.edit', $product).'" class="fw-medium d-block text-reset text-truncate">'.e($product->name).'</a><span class="text-secondary small">'.e($product->product_type === 'digital' ? 'Produk digital' : 'Barang fisik').'</span>',
                            'sku' => $product->sku ?: '—',
                            'stock' => '<span class="fw-medium">'.e(Currency::number($product->current_stock)).'</span>',
                            'threshold' => Currency::number($product->low_stock_threshold ?? 0),
                            'status' => (int) $product->current_stock <= 0
                                ? '<span class="badge bg-danger-lt text-danger rounded-pill">Habis</span>'
                                : ((int) $product->current_stock <= (int) ($product->low_stock_threshold ?? 0)
                                    ? '<span class="badge bg-warning-lt text-warning rounded-pill">Menipis</span>'
                                    : '<span class="badge bg-success-lt text-success rounded-pill">Aman</span>'),
                            'actions' => '<a href="'.route('vendor.products.edit', $product).'" class="btn btn-sm btn-ghost-light">Detail</a>',
                        ])->all()
                    )
                    ->empty('Tidak ada produk yang cocok dengan filter.')
            </x-slot:table>
        </x-admin.table>
    </x-admin.card>

    <div class="mt-3"><x-admin.pagination :paginator="products" /></div>

    <x-admin.card title="Penyesuaian stok" icon="sliders" class="mt-3">
        <x-admin.alert type="info">
            Penyesuaian stok dicatat sebagai barang masuk, barang keluar atau penyesuaian.
            Setiap perubahan menulis jejak audit dan tidak dapat melebihi stok tersedia.
        </x-admin.alert>

        <form method="POST" action="{{ route('vendor.inventory.adjust') }}">
            @csrf
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <x-admin.form-field
                        name="product_id"
                        label="Produk"
                        type="select"
                        :options="$products->pluck('name', 'id')->all()"
                        placeholder="Pilih produk"
                        required
                    />
                </div>
                <div class="col-6 col-md-2">
                    <x-admin.form-field name="mode" label="Jenis" type="select" required :options="[
                        'increase' => 'Tambah',
                        'decrease' => 'Kurangi',
                        'set' => 'Tetapkan',
                    ]" />
                </div>
                <div class="col-6 col-md-2">
                    <x-admin.form-field name="quantity" label="Jumlah" type="number" :min="0" required />
                </div>
                <div class="col-12 col-md-4">
                    <x-admin.form-field name="reason" label="Alasan" required placeholder="mis. Hasil opname gudang" />
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                    <x-admin.icon name="check" :size="16" class="me-1" />
                    <span>Terapkan</span>
                </button>
            </div>
        </form>
    </x-admin.card>
@endsection
