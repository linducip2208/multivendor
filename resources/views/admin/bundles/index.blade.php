@extends('layouts.admin')

@section('title', 'Bundel Produk')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Commerce', ['label' => 'Bundling']]" />
@endsection

@section('content')
    <x-admin.page-header title="Bundel Produk" subtitle="Grup produk yang dijual bersama dengan potongan harga.">
        <x-slot:actions>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bundle-modal" aria-haspopup="dialog">
                <x-admin.icon name="plus" :size="14" /> Buat Bundel
            </button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.card title="Daftar Bundel" icon="boxes" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Judul</th>
                        <th scope="col" class="text-end">Diskon</th>
                        <th scope="col" class="text-end">Produk</th>
                        <th scope="col">Dibuat</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bundles as $bundle)
                        <tr>
                            <td class="fw-semibold">{{ $bundle['title'] }}</td>
                            <td class="text-end">
                                <x-admin.badge :text="number_format($bundle['discount_percentage'], 1, ',', '.').'%" color="success" pill />
                            </td>
                            <td class="text-end">{{ number_format($bundle['product_count'], 0, ',', '.') }}</td>
                            <td class="text-nowrap">{{ $bundle['created_at'] }}</td>
                            <td class="text-center">
                                <x-admin.badge :text="$bundle['is_active'] ? 'Aktif' : 'Nonaktif'" :color="$bundle['is_active'] ? 'success' : 'secondary'" pill />
                            </td>
                            <td class="text-end">
                                <x-admin.confirmation-form
                                    :action="route('admin.bundles.destroy', $bundle['id'])"
                                    message="Bundel beserta seluruh produknya akan dihapus. Lanjutkan?"
                                    label="Hapus"
                                    variant="outline-danger"
                                    icon="trash"
                                    size="btn-sm"
                                />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-admin.empty-state
                                    icon="boxes"
                                    title="Belum ada bundel"
                                    text="Buat bundling pertama untuk menawarkan beberapa produk sekaligus."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <x-admin.modal id="bundle-modal" title="Buat Bundel" icon="plus" size="sm">
        <form method="POST" action="{{ route('admin.bundles.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-12">
                    <x-admin.form-field name="title" label="Judul Bundle" required :maxlength="160" />
                </div>
                <div class="col-6">
                    <x-admin.form-field name="discount_percentage" label="Diskon (%)" type="number" value="10" :min="0" :max="100" :step="0.01" />
                </div>
                <div class="col-6 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="bundle-active" checked>
                        <label class="form-check-label" for="bundle-active">Aktif</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="bundle-products">Produk</label>
                    <select class="form-select" id="bundle-products" name="product_ids[]" multiple size="8" required data-multi-select>
                        @foreach ($productOptions as $product)
                            <option value="{{ $product['id'] }}">{{ $product['name'] }} — {{ $product['price_formatted'] }}</option>
                        @endforeach
                    </select>
                    <small class="text-secondary">Tahan Ctrl/Cmd untuk memilih beberapa produk.</small>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Bundel</button>
            </div>
        </form>
    </x-admin.modal>
@endsection
