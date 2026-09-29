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
        <span>Pesanan ditahan</span>
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
                    <x-admin.pagination :paginator="$products" />
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
                <x-admin.form-field name="customer_name" label="Nama pelanggan" placeholder="Pelanggan langsung" />
                <x-admin.form-field name="customer_phone" label="Telepon" type="tel" placeholder="08xx" />
                <x-admin.form-field name="discount" label="Diskon" type="number" :min="0" :step="1" :prefix="Currency::config()['symbol']" />
                <div class="alert alert-info py-2 px-3 small">
                    Diskon per item + pajak per item didukung via API (<code>items[][discount]</code>, <code>items[][tax_rate]</code>).
                    Shift kasir mencatat selisih kas (diharapkan vs dihitung), retur POS kembali ke stok, dan barkode massal tersedia di faktur.
                </div>
                <x-admin.form-field name="payment_method" label="Metode pembayaran" type="select" :options="[
                    'cash' => 'Tunai',
                    'qris' => 'QRIS',
                    'transfer' => 'Transfer',
                ]" />

                <div class="border rounded p-2 mb-2" data-pos-tenderbox>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-semibold small">Split tender (opsional)</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-pos-tender-add>
                            + Tambah pembayaran
                        </button>
                    </div>
                    <p class="text-secondary small mb-2">
                        Gabungkan tunai + QRIS/transfer dalam satu struk. Total semua tender harus pas
                        dengan total belanja — server menolak bila selisih.
                    </p>
                    <div data-pos-tender-rows class="d-grid gap-2"></div>
                    <div class="small mt-1" data-pos-tender-balance role="status" aria-live="polite"></div>
                </div>

                <p class="text-secondary small mb-2">
                    Nomor telepon pelanggan dipakai untuk struk digital: tautan verifikasi + QR pada
                    struk dan tombol kirim via WhatsApp.
                </p>

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
                        <span>Simpan sebagai pesanan ditahan</span>
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
            const tenderRowsNode = document.querySelector('[data-pos-tender-rows]');
            const tenderAddButton = document.querySelector('[data-pos-tender-add]');
            const tenderBalanceNode = document.querySelector('[data-pos-tender-balance]');

            if (!cartNode || !checkoutButton) {
                return;
            }

            let cart = [];
            let tenders = [];
            // Idempotency kasir: satu kunci per muat halaman, dikirim pada
            // setiap submit agar double-tap tidak mencetak dua struk.
            const idempotencyKey = 'pos-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);

            const tenderMethods = { cash: 'Tunai', qris: 'QRIS', transfer: 'Transfer', debit: 'Debit', ewallet: 'E-Wallet' };

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

                renderTenders(total);

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
                    remove.textContent = '�';
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

                const total = cartTotal();
                const active = activeTenders();

                // Cegah submit split tender yang tidak pas sejak di kasir;
                // server tetap memvalidasi ulang secara atomik.
                if (!hold && active.length > 0) {
                    const paid = active.reduce(function (sum, tender) { return sum + (parseFloat(tender.amount) || 0); }, 0);
                    if (Math.round(paid - total) !== 0) {
                        showError('Total split tender harus pas dengan total belanja.');
                        return;
                    }
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
                            payment_method: active.length > 0 ? 'split' : methodInput.value,
                            tenders: active.map(function (tender) { return { method: tender.method, amount: parseFloat(tender.amount) || 0 }; }),
                            idempotency_key: idempotencyKey,
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

                    // Struk digital: tawarkan kirim WA bila nomor tersedia.
                    let message = 'Pesanan ' + data.order_number + ' berhasil disimpan. Total: ' + (data.total_formatted || data.total);
                    if (data.wa_url) {
                        message += '\nKirim struk digital via WhatsApp?';
                        window.alert(message);
                        window.open(data.wa_url, '_blank', 'noopener');
                    } else {
                        window.alert(message);
                    }
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
            function cartTotal() {
                const discount = Math.max(0, parseFloat(discountInput.value) || 0);
                const subtotal = cart.reduce(function (sum, line) { return sum + (line.price * line.qty); }, 0);
                return Math.max(0, subtotal - discount);
            }

            function activeTenders() {
                return tenders.filter(function (tender) { return (parseFloat(tender.amount) || 0) > 0; });
            }

            function renderTenders(total) {
                if (!tenderRowsNode) {
                    return;
                }

                tenderRowsNode.innerHTML = '';

                tenders.forEach(function (tender, index) {
                    const row = document.createElement('div');
                    row.className = 'd-flex gap-2 align-items-center';

                    const method = document.createElement('select');
                    method.className = 'form-select form-select-sm';
                    method.style.maxWidth = '130px';
                    Object.keys(tenderMethods).forEach(function (key) {
                        const option = document.createElement('option');
                        option.value = key;
                        option.textContent = tenderMethods[key];
                        if (tender.method === key) {
                            option.selected = true;
                        }
                        method.appendChild(option);
                    });
                    method.addEventListener('change', function () {
                        tender.method = method.value;
                        renderTenders(cartTotal());
                    });

                    const amount = document.createElement('input');
                    amount.type = 'number';
                    amount.className = 'form-control form-control-sm';
                    amount.min = 0;
                    amount.step = 1;
                    amount.placeholder = 'Nominal';
                    amount.value = tender.amount;
                    amount.setAttribute('aria-label', 'Nominal pembayaran ' + (index + 1));
                    amount.addEventListener('input', function () {
                        tender.amount = amount.value;
                        renderTenderBalance(cartTotal());
                    });

                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'btn btn-sm btn-ghost-light';
                    remove.setAttribute('aria-label', 'Hapus pembayaran ' + (index + 1));
                    remove.textContent = '×';
                    remove.addEventListener('click', function () {
                        tenders.splice(index, 1);
                        renderTenders(cartTotal());
                    });

                    row.appendChild(method);
                    row.appendChild(amount);
                    row.appendChild(remove);
                    tenderRowsNode.appendChild(row);
                });

                renderTenderBalance(total);
            }

            function renderTenderBalance(total) {
                if (!tenderBalanceNode) {
                    return;
                }

                const active = activeTenders();

                if (active.length === 0) {
                    tenderBalanceNode.innerHTML = '<span class="text-secondary">Tanpa split tender: memakai satu metode pembayaran di atas.</span>';
                    return;
                }

                const paid = active.reduce(function (sum, tender) { return sum + (parseFloat(tender.amount) || 0); }, 0);
                const diff = Math.round(paid - total);

                if (diff === 0) {
                    tenderBalanceNode.innerHTML = '<span class="text-success fw-medium">Pas: ' + format(paid) + ' dari ' + format(total) + '.</span>';
                } else if (diff < 0) {
                    tenderBalanceNode.innerHTML = '<span class="text-danger fw-medium">Kurang ' + format(-diff) + ' (terkumpul ' + format(paid) + ' dari ' + format(total) + ').</span>';
                } else {
                    tenderBalanceNode.innerHTML = '<span class="text-danger fw-medium">Lebih ' + format(diff) + ' (terkumpul ' + format(paid) + ' dari ' + format(total) + ').</span>';
                }
            }

            if (tenderAddButton) {
                tenderAddButton.addEventListener('click', function () {
                    if (tenders.length >= 5) {
                        showError('Maksimal 5 metode pembayaran dalam satu struk.');
                        return;
                    }
                    clearError();
                    tenders.push({ method: 'cash', amount: '' });
                    renderTenders(cartTotal());
                });
            }

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