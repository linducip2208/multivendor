@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Ubah pesanan '.$order->order_number)
@section('subtitle', 'Subtotal, pajak, dan total dihitung ulang oleh server')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Pesanan', 'href' => route('vendor.orders.index')],
    ['label' => $order->order_number, 'href' => route('vendor.orders.show', $order)],
    ['label' => 'Ubah'],
])

@section('actions')
    <a href="{{ route('vendor.orders.show', $order) }}" class="btn btn-outline-secondary">
        <x-admin.icon name="arrow-left" :size="16" class="me-1" />
        <span>Kembali</span>
    </a>
@endsection

@section('content')
    @if (! in_array($order->order_status, $editableStatuses, true))
        <x-admin.alert type="warning" title="Pesanan tidak dapat diedit">
            Status <strong>{{ str_replace('_', ' ', (string) $order->order_status) }}</strong> sudah melewati tahap pemrosesan.
            Gunakan alur pembatalan atau retur untuk mengubah pesanan ini.
        </x-admin.alert>
    @endif

    <x-admin.alert type="info" title="Bagaimana perhitungan ulang bekerja">
        Harga diambil dari katalog saat ini. Setiap perubahan disimpan di riwayat status pesanan,
        dan pembulatan uang memakai <span class="font-monospace">App\Support\Money</span> agar tidak pernah
        berbeda antara layar dan database.
    </x-admin.alert>

    <form method="POST" action="{{ route('vendor.orders.edit-update', $order) }}">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <x-admin.card title="Item pesanan" icon="package" :padding="false" flush>
                    <div class="table-responsive">
                        <table class="table admin-table mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Produk</th>
                                    <th scope="col" class="text-end" style="width: 150px;">Harga</th>
                                    <th scope="col" style="width: 130px;">Jumlah</th>
                                    <th scope="col" style="width: 160px;">Diskon item</th>
                                    <th scope="col" class="text-end" style="width: 150px;">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody data-order-items>
                                @foreach ($order->items as $item)
                                    <tr>
                                        <td>
                                            <input type="hidden" name="items[{{ $loop->index }}][product_id]" value="{{ $item->product_id }}">
                                            <input type="hidden" name="items[{{ $loop->index }}][product_variant_id]" value="{{ $item->product_variant_id }}">
                                            <span class="fw-medium d-block">{{ $item->product?->name ?? 'Produk tidak tersedia' }}</span>
                                            <span class="text-secondary small">{{ $item->product?->sku }}</span>
                                        </td>
                                        <td class="text-end text-nowrap">{{ Currency::format($item->price) }}</td>
                                        <td>
                                            <input class="form-control form-control-sm" type="number" min="1" max="1000" name="items[{{ $loop->index }}][quantity]" value="{{ max(1, (int) $item->quantity) }}" required>
                                        </td>
                                        <td>
                                            <input class="form-control form-control-sm" type="number" min="0" step="0.01" name="items[{{ $loop->index }}][discount]" value="{{ (float) $item->discount }}">
                                        </td>
                                        <td class="text-end fw-medium text-nowrap">{{ Currency::format($item->sub_total) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-admin.card>

                <x-admin.card title="Tambah item" icon="plus" class="mt-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-8">
                            <x-admin.form-field name="add_product_id" label="Produk dari katalog" type="select" placeholder="Pilih produk" :options="$products->mapWithKeys(fn ($product) => [$product->id => $product->name.' — '.Currency::format($product->effective_price)])->all()" />
                        </div>
                        <div class="col-6 col-md-2">
                            <x-admin.form-field name="add_quantity" label="Jumlah" type="number" :min="1" :value="1" />
                        </div>
                        <div class="col-6 col-md-2">
                            <button type="button" class="btn btn-outline-primary w-100" data-add-line>
                                <x-admin.icon name="plus" :size="16" class="me-1" />
                                <span>Tambah</span>
                            </button>
                        </div>
                    </div>
                </x-admin.card>
            </div>

            <div class="col-12 col-xl-4">
                <x-admin.card title="Ringkasan" icon="receipt" class="mb-3">
                    <dl class="row mb-0 small">
                        <dt class="col-7 text-secondary fw-normal">Subtotal saat ini</dt>
                        <dd class="col-5 text-end fw-medium">{{ Currency::format($order->sub_total) }}</dd>
                        <dt class="col-7 text-secondary fw-normal">Pajak saat ini</dt>
                        <dd class="col-5 text-end fw-medium">{{ Currency::format($order->tax) }}</dd>
                        <dt class="col-7 text-secondary fw-normal">Diskon</dt>
                        <dd class="col-5 text-end fw-medium">{{ Currency::format($order->discount) }}</dd>
                        <dt class="col-7 text-secondary fw-normal">Kupon</dt>
                        <dd class="col-5 text-end fw-medium">{{ Currency::format($order->coupon_discount) }}</dd>
                        <dt class="col-7 text-secondary fw-normal">Ongkir</dt>
                        <dd class="col-5 text-end fw-medium">{{ Currency::format($order->shipping_cost) }}</dd>
                        <dt class="col-7 border-top pt-2 fw-semibold">Total</dt>
                        <dd class="col-5 text-end border-top pt-2 fw-semibold">{{ Currency::format($order->total) }}</dd>
                    </dl>
                </x-admin.card>

                <x-admin.card title="Pengiriman & catatan" icon="truck">
                    <x-admin.form-field name="shipping_cost" label="Ongkir" type="number" :min="0" :step="0.01" :value="$order->shipping_cost" />
                    <x-admin.form-field name="discount" label="Diskon tambahan" type="number" :min="0" :step="0.01" :value="$order->discount" />
                    <x-admin.form-field name="shipping_tracking_id" label="Nomor resi" :value="$order->shipping_tracking_id" />
                    <x-admin.form-field name="note" label="Catatan internal" type="textarea" :rows="3" :value="$order->note" />

                    <div class="d-flex gap-2">
                        <a href="{{ route('vendor.orders.show', $order) }}" class="btn btn-outline-secondary flex-grow-1">Batal</a>
                        <button type="submit" class="btn btn-primary flex-grow-1" data-confirm="Simpan perubahan pada pesanan ini?">
                            <x-admin.icon name="check" :size="16" class="me-1" />
                            <span>Simpan</span>
                        </button>
                    </div>
                </x-admin.card>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script data-order-editor>
        (function () {
            const body = document.querySelector('[data-order-items]');
            const select = document.getElementById('field-add-product-id');
            const quantity = document.getElementById('field-add-quantity');
            const button = document.querySelector('[data-add-line]');

            if (!body || !select || !button) {
                return;
            }

            button.addEventListener('click', function () {
                if (!select.value) {
                    return;
                }

                const index = body.querySelectorAll('tr').length;
                const option = select.options[select.selectedIndex];
                const row = document.createElement('tr');

                row.innerHTML = [
                    '<td>',
                    '<input type="hidden" name="items[' + index + '][product_id]" value="' + option.value + '">',
                    '<input type="hidden" name="items[' + index + '][product_variant_id]" value="">',
                    '<span class="fw-medium d-block"></span>',
                    '</td>',
                    '<td class="text-end text-nowrap">' + (option.dataset.price || '0') + '</td>',
                    '<td><input class="form-control form-control-sm" type="number" min="1" max="1000" name="items[' + index + '][quantity]" value="' + (quantity.value || '1') + '" required></td>',
                    '<td><input class="form-control form-control-sm" type="number" min="0" step="0.01" name="items[' + index + '][discount]" value="0"></td>',
                    '<td class="text-end fw-medium text-nowrap">' + (option.dataset.price || '0') + '</td>'
                ].join('');

                row.querySelector('span').textContent = option.dataset.name || option.textContent;
                body.appendChild(row);
                select.value = '';
            });
        })();
    </script>
@endpush
