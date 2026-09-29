@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Kasir (POS)')
@section('subtitle', $shop->name)

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'POS'],
])

@section('actions')
    <a href="{{ route('vendor.pos.held') }}" class="btn btn-outline-secondary">
        <x-admin.icon name="clock" :size="16" class="me-1" />
        <span>Hold order</span>
    </a>
@endsection

@push('head')
<style>
    .pos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: .75rem; }
    .pos-tile { cursor: pointer; border-radius: .75rem; padding: .625rem; text-align: center; background: var(--tblr-bg-surface); border: 1px solid var(--tblr-border-color); transition: transform .12s ease, box-shadow .12s ease; height: 100%; }
    .pos-tile:hover { transform: translateY(-2px); box-shadow: var(--tblr-box-shadow-sm); }
    .pos-tile.is-picked { border-color: var(--tblr-primary); background: var(--tblr-primary-lt); }
    .pos-tile.is-out { opacity: .5; cursor: not-allowed; }
</style>
@endpush

@section('content')
    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-admin.card title="Pilih produk" icon="package" :padding="false">
                <x-slot:actions>
                    <label class="form-label small mb-1" for="pos-search">Cari</label>
                    <input class="form-control form-control-sm" type="search" id="pos-search" value="{{ $search }}" placeholder="Nama produk" style="min-width: 200px;">
                </x-slot:actions>

                <x-slot:footer>
                    {{ $products->links() }}
                </x-slot:footer>

                @if ($products->isEmpty())
                    <x-admin.empty-state icon="package" title="Belum ada produk" text="Tambahkan produk yang sudah disetujui sebelum membuka kasir." compact />
                @else
                    <div class="pos-grid" data-pos-grid>
                        @foreach ($products as $product)
                            @php
                                $image = $product->thumbnail
                                    ? (str_starts_with($product->thumbnail, 'http') ? $product->thumbnail : url('img/'.ltrim($product->thumbnail, '/')))
                                    : null;
                            @endphp
                            <button
                                type="button"
                                class="pos-tile {{ (int) $product->current_stock <= 0 ? 'is-out' : '' }}"
                                data-pos-product
                                data-id="{{ $product->id }}"
                                data-name="{{ $product->name }}"
                                data-price="{{ (float) $product->effective_price }}"
                                data-stock="{{ (int) $product->current_stock }}"
                                @disabled((int) $product->current_stock <= 0)
                            >
                                <span class="d-block mb-1" style="height: 64px;">
                                    @if ($image)
                                        <img src="{{ $image }}" alt="" loading="lazy" style="width: 100%; height: 100%; object-fit: contain;">
                                    @else
                                        <x-admin.icon name="package" :size="26" class="text-secondary" />
                                    @endif
                                </span>
                                <span class="d-block fw-semibold small text-truncate mb-1">{{ $product->name }}</span>
                                <span class="d-block fw-bold text-primary">{{ Currency::format($product->effective_price) }}</span>
                                <span class="d-block text-secondary small">Stok {{ Currency::number($product->current_stock) }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </x-admin.card>
        </div>

        <div class="col-12 col-xl-4">
            <x-admin.card title="Keranjang" icon="shopping-cart" class="mb-3">
                <div data-pos-cart style="max-height: 46vh; overflow-y: auto;">
                    <x-admin.empty-state icon="shopping-cart" text="Klik produk untuk menambahkan ke keranjang." compact />
                </div>
            </x-admin.card>

            <x-admin.card title="Pembayaran" icon="cash-coin">
                <x-admin.form-field name="customer_name" label="Nama pelanggan" placeholder="Pelanggan walk-in" />
                <x-admin.form-field name="customer_phone" label="Telepon" type="tel" placeholder="08xx" />
                <x-admin.form-field name="discount" label="Diskon" type="number" :min="0" :step="1" :prefix="Currency::config()['symbol']" />
                <x-admin.form-field name="payment_method" label="Metode pembayaran" type="select" :options="[
                    'cash' => 'Tunai',
                    'qris' => 'QRIS',
                    'transfer' => 'Transfer',
                ]" />

                <dl class="row mb-2 small">
                    <dt class="col-6 text-secondary fw-normal">Subtotal</dt>
                    <dd class="col-6 text-end" data-pos-subtotal>{{ Currency::format(0) }}</dd>
                    <dt class="col-6 fw-semibold border-top pt-2">Total</dt>
                    <dd class="col-6 text-end fw-semibold border-top pt-2 fs-5" data-pos-total>{{ Currency::format(0) }}</dd>
                </dl>

                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-success" data-pos-checkout disabled>
                        <x-admin.icon name="cash-coin" :size="16" class="me-1" />
                        <span>Bayar (F8)</span>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-pos-hold disabled>
                        <x-admin.icon name="clock" :size="16" class="me-1" />
                        <span>Simpan sebagai hold</span>
                    </button>
                </div>

                <div class="alert alert-danger mt-3 mb-0 py-2 small d-none" data-pos-error role="alert"></div>
            </x-admin.card>
        </div>
    </div>
@endsection

@push('scripts')
    <script data-pos-app>
        (function () {
            const currency = @json(\App\Support\Currency::config());
            const storeUrl = @json(route('vendor.pos.store'));
            const csrf = @json(csrf_token());

            const cartNode = document.querySelector('[data-pos-cart]');
            const subtotalNode = document.querySelector('[data-pos-subtotal]');
            const totalNode = document.querySelector('[data-pos-total]');
            const errorNode = document.querySelector('[data-pos-error]');
            const discountInput = document.getElementById('field-discount');
            const nameInput = document.getElementById('field-customer_name');
            const phoneInput = document.getElementById('field-customer_phone');
            const methodInput = document.getElementById('field-payment_method');
            const checkoutButton = document.querySelector('[data-pos-checkout]');
            const holdButton = document.querySelector('[data-pos-hold]');
            const searchInput = document.getElementById('pos-search');

            if (!cartNode || !checkoutButton) {
                return;
            }

            let cart = [];

            function format(amount) {
                return currency.symbol + ' ' + new Intl.NumberFormat('id-ID').format(amount);
            }

            function showError(message) {
                errorNode.textContent = message;
                errorNode.classList.remove('d-none');
            }

            function clearError() {
                errorNode.textContent = '';
                errorNode.classList.add('d-none');
            }

            function render() {
                const discount = Math.max(0, parseFloat(discountInput.value) || 0);
                const subtotal = cart.reduce(function (sum, line) { return sum + (line.price * line.qty); }, 0);
                const total = Math.max(0, subtotal - discount);

                subtotalNode.textContent = format(subtotal);
                totalNode.textContent = format(total);

                checkoutButton.disabled = holdButton.disabled = cart.length === 0;
                cartNode.innerHTML = '';

                if (cart.length === 0) {
                    const empty = document.createElement('p');
                    empty.className = 'text-center text-secondary py-4 mb-0 small';
                    empty.textContent = 'Keranjang masih kosong.';
                    cartNode.appendChild(empty);
                    return;
                }

                cart.forEach(function (line, index) {
                    const row = document.createElement('div');
                    row.className = 'd-flex align-items-start gap-2 py-2 border-bottom';

                    const info = document.createElement('div');
                    info.className = 'flex-grow-1 min-w-0';

                    const label = document.createElement('div');
                    label.className = 'fw-medium small text-truncate';
                    label.textContent = line.name;

                    const qty = document.createElement('input');
                    qty.type = 'number';
                    qty.className = 'form-control form-control-sm mt-1';
                    qty.style.maxWidth = '90px';
                    qty.min = 1;
                    qty.max = line.stock;
                    qty.value = line.qty;
                    qty.addEventListener('change', function () {
                        line.qty = Math.max(1, Math.min(line.stock, parseInt(qty.value, 10) || 1));
                        render();
                    });

                    info.appendChild(label);
                    info.appendChild(qty);

                    const amount = document.createElement('div');
                    amount.className = 'fw-semibold small text-nowrap';
                    amount.textContent = format(line.price * line.qty);

                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'btn btn-sm btn-ghost-light';
                    remove.setAttribute('aria-label', 'Hapus ' + line.name);
                    remove.textContent = '×';
                    remove.addEventListener('click', function () {
                        cart.splice(index, 1);
                        render();
                    });

                    row.appendChild(info);
                    row.appendChild(amount);
                    row.appendChild(remove);
                    cartNode.appendChild(row);
                });
            }

            Array.prototype.forEach.call(document.querySelectorAll('[data-pos-product]'), function (tile) {
                tile.addEventListener('click', function () {
                    const id = tile.dataset.id;
                    const stock = Number(tile.dataset.stock);
                    const existing = cart.find(function (line) { return line.id === id; });

                    if (existing) {
                        if (existing.qty >= stock) {
                            showError('Stok ' + tile.dataset.name + ' tidak mencukupi.');
                            return;
                        }
                        existing.qty += 1;
                    } else {
                        cart.push({
                            id: id,
                            name: tile.dataset.name,
                            price: Number(tile.dataset.price),
                            qty: 1,
                            stock: stock
                        });
                    }

                    clearError();
                    tile.classList.add('is-picked');
                    window.setTimeout(function () { tile.classList.remove('is-picked'); }, 160);
                    render();
                });
            });

            async function submit(hold) {
                if (cart.length === 0) {
                    return;
                }

                checkoutButton.disabled = holdButton.disabled = true;

                try {
                    const response = await fetch(storeUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: JSON.stringify({
                            items: cart.map(function (line) { return { product_id: line.id, quantity: line.qty }; }),
                            discount: discountInput.value || 0,
                            customer_name: nameInput.value,
                            customer_phone: phoneInput.value,
                            payment_method: methodInput.value,
                            hold: hold
                        })
                    });

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        const payload = data.errors || {};
                        const first = Object.keys(payload).map(function (key) { return payload[key][0]; })[0];
                        showError(first || data.message || 'Transaksi gagal diproses.');
                        render();
                        return;
                    }

                    window.alert('Pesanan ' + data.order_number + ' berhasil disimpan. Total: ' + (data.total_formatted || data.total));
                    window.location.reload();
                } catch (error) {
                    showError('Tidak dapat terhubung ke server.');
                    render();
                }
            }

            checkoutButton.addEventListener('click', function () { submit(false); });
            holdButton.addEventListener('click', function () { submit(true); });
            discountInput.addEventListener('input', render);

            if (searchInput) {
                searchInput.addEventListener('input', function (event) {
                    const needle = event.target.value.toLowerCase();
                    Array.prototype.forEach.call(document.querySelectorAll('[data-pos-product]'), function (tile) {
                        tile.style.display = tile.dataset.name.toLowerCase().includes(needle) ? '' : 'none';
                    });
                });
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'F8') {
                    event.preventDefault();
                    submit(false);
                }
            });

            render();
        })();
    </script>
@endpush