@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Produk')
@section('subtitle', 'Katalog toko Anda')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Produk'],
])

@section('actions')
    <a href="{{ route('vendor.products.low-stock') }}" class="btn btn-outline-secondary">
        <x-admin.icon name="alert-triangle" :size="16" class="me-1" />
        <span>Stok menipis</span>
    </a>
    <a href="{{ route('vendor.products.create') }}" class="btn btn-primary">
        <x-admin.icon name="plus" :size="16" class="me-1" />
        <span>Tambah produk</span>
    </a>
@endsection

@section('content')
    <x-admin.filters
        :action="route('vendor.products.index')"
        :filters="[
            ['name' => 'search', 'label' => 'Cari produk', 'placeholder' => 'Nama produk'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => [
                '' => 'Semua',
                'pending' => 'Menunggu tinjauan',
                'approved' => 'Tayang',
                'rejected' => 'Ditolak',
                'suspended' => 'Ditangguhkan',
            ]],
        ]"
    />

    <form method="POST" action="{{ route('vendor.products.bulk-price') }}" data-confirm="Terapkan perubahan harga pada produk terpilih?">
        @csrf
        @method('PATCH')

        <x-admin.card title="Katalog" icon="package" :padding="false">
            <x-slot:actions>
                <label class="form-check form-switch d-inline-flex align-items-center gap-2 mb-0 me-2">
                    <input class="form-check-input" type="checkbox" data-select-all aria-label="Pilih semua">
                    <span class="form-check-label small">Pilih semua</span>
                </label>
                <button type="submit" class="btn btn-sm btn-primary" data-bulk-submit disabled>
                    <x-admin.icon name="edit" :size="14" class="me-1" />
                    <span>Ubah harga terpilih</span>
                </button>
            </x-slot:actions>

            <x-admin.table>
                <x-slot:table>
                    \App\Support\TableBuilder::make()
                        ->columns([
                            'check' => ['label' => 'Pilih', 'width' => '70px', 'class' => 'text-center'],
                            'product' => ['label' => 'Produk', 'width' => '34%'],
                            'sku' => ['label' => 'SKU'],
                            'price' => ['label' => 'Harga', 'align' => 'end'],
                            'stock' => ['label' => 'Stok', 'align' => 'end'],
                            'status' => ['label' => 'Status'],
                            'actions' => ['label' => '', 'align' => 'end', 'width' => '170px'],
                        ])
                        ->rows(
                            $products->map(fn ($product) => [
                                'check' => '<input class="form-check-input" type="checkbox" name="products[]" value="'.(int) $product->id.'" data-row-check aria-label="Pilih '.e($product->name).'">',
                                'product' => '<a href="'.route('vendor.products.edit', $product).'" class="fw-medium d-block text-reset text-truncate">'.e($product->name).'</a><span class="text-secondary small">'.e($product->product_type === 'digital' ? 'Digital' : 'Fisik').'</span>',
                                'sku' => e($product->sku ?: '—'),
                                'price' => '<span class="fw-medium text-nowrap">'.e(Currency::format($product->effective_price)).'</span>',
                                'stock' => '<span class="text-nowrap">'.e(Currency::number($product->current_stock)).'</span>',
                                'status' => $__status($product->status),
                                'actions' => '<div class="d-flex gap-1 justify-content-end">'
                                    .'<a href="'.route('vendor.products.show', $product).'" class="btn btn-sm btn-ghost-light">Lihat</a>'
                                    .'<a href="'.route('vendor.products.edit', $product).'" class="btn btn-sm btn-ghost-light">Ubah</a>'
                                    .'</div>',
                            ])->all()
                        )
                        ->empty('Belum ada produk yang cocok dengan filter.')
                </x-slot:table>
            </x-admin.table>
        </x-admin.card>

        <x-admin.card title="Ubah harga massal" icon="edit" class="mt-3">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <x-admin.form-field name="mode" label="Metode" type="select" required :options="[
                        'increase' => 'Naikkan sebesar',
                        'decrease' => 'Turunkan sebesar',
                        'margin' => 'Set margin persen',
                        'set' => 'Tetapkan harga',
                    ]" />
                </div>
                <div class="col-6 col-md-3">
                    <x-admin.form-field name="value" label="Nilai" type="number" :min="0" :step="0.01" required help="Untuk margin, isi persentase." />
                </div>
                <div class="col-12 col-md-3">
                    <x-admin.form-field name="status" label="Batasi status" type="select" placeholder="Semua status" :options="[
                        'pending' => 'Menunggu tinjauan',
                        'approved' => 'Tayang',
                        'suspended' => 'Ditangguhkan',
                    ]" />
                </div>
                <div class="col-12 col-md-3">
                    <button type="submit" class="btn btn-primary w-100" data-bulk-submit disabled>
                        <x-admin.icon name="check" :size="16" class="me-1" />
                        <span>Terapkan</span>
                    </button>
                </div>
            </div>
        </x-admin.card>
    </form>

    <x-admin.pagination :paginator="$products" class="mt-3" />
@endsection

@push('scripts')
    <script data-bulk-price>
        (function () {
            const checks = Array.prototype.slice.call(document.querySelectorAll('[data-row-check]'));
            const selectAll = document.querySelector('[data-select-all]');
            const buttons = Array.prototype.slice.call(document.querySelectorAll('[data-bulk-submit]'));

            function sync() {
                const any = checks.some(function (input) { return input.checked; });
                buttons.forEach(function (button) { button.disabled = !any; });
            }

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    checks.forEach(function (input) { input.checked = selectAll.checked; });
                    sync();
                });
            }

            checks.forEach(function (input) { input.addEventListener('change', sync); });
            sync();
        })();
    </script>
@endpush
