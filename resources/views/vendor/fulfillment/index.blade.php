@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Antrean pengiriman')
@section('subtitle', $total.' pesanan menunggu dikirim')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Pesanan', 'href' => route('vendor.orders.index')],
    ['label' => 'Pengiriman'],
])

@section('actions')
    <a href="{{ route('vendor.orders.index') }}" class="btn btn-outline-secondary">
        <x-admin.icon name="list" :size="16" class="me-1" />
        <span>Semua pesanan</span>
    </a>
@endsection

@section('content')
    <x-admin.card>
        <h3 class="h6 mb-2">Pemenuhan massal</h3>
        <p class="text-secondary small mb-2">Pilih pesanan di bawah (centang), lalu kirim massal dengan satu resi dasar, cetak label massal, atau ekspor CSV. Status massal (dikonfirmasi/diproses/dikemas) diproses per pesanan secara atomik.</p>
        @if (! empty($bulkResult))
            <div class="alert alert-info py-2 px-3 small">
                Massal: {{ count($bulkResult['ok'] ?? []) }} berhasil, {{ count($bulkResult['fail'] ?? []) }} gagal.
            </div>
        @endif
        @if (! empty($labels))
            <div class="table-responsive mb-2">
                <table class="table table-sm">
                    <thead><tr><th>Pesanan</th><th>Kurir</th><th>Layanan</th><th>Resi</th><th>Berat</th><th>Biaya</th><th>Manifest</th><th>Ambil</th></tr></thead>
                    <tbody>
                        @foreach ($labels as $label)
                            <tr>
                                <td>{{ $label['nomor_pesanan'] }}</td><td>{{ $label['kurir'] }}</td>
                                <td>{{ $label['layanan'] }}</td><td><code>{{ $label['resi'] }}</code></td>
                                <td>{{ $label['berat'] }}</td><td>{{ $label['biaya'] }}</td>
                                <td class="small">{{ $label['manifest_no'] ?? '' }}</td>
                                <td>
                                    @if (! empty($label['is_pickup']))
                                        <span class="badge bg-info-lt text-info rounded-pill">Ambil{{ ! empty($label['pickup_code']) ? ': '.$label['pickup_code'] : '' }}</span>
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if (! empty($export))
            <div class="alert alert-success py-2 px-3 small">
                Ekspor siap: {{ count($export) - 1 }} baris (header: {{ implode(', ', $export[0] ?? []) }}). Salin dari tabel label atau unduh via laporan pesanan.
            </div>
        @endif
        <form method="GET" action="{{ route('vendor.fulfillment.index') }}" class="row g-2" id="bulk-fulfillment-form">
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" name="format" value="labels" class="btn btn-outline-primary btn-sm">Cetak label massal</button>
                    <button type="submit" name="format" value="csv" class="btn btn-outline-secondary btn-sm">Ekspor CSV</button>
                </div>
                <p class="text-secondary small mt-2 mb-0">Centang pesanan pada daftar, lalu klik tombol di atas. Untuk kirim massal, gunakan formulir kirim pada salah satu pesanan dengan menambahkan <code>order_ids[]</code>.</p>
            </div>
        </form>
    </x-admin.card>

    @php
        $vendorShopId = (int) (auth('vendor')->user()->shop_id ?? 0);
        $manifestRecapRows = [];
        $pickupQueueRows = collect();
        $returnPickupRows = collect();
        try {
            $manifestRecapRows = app(\App\Services\Vendor\VendorFulfillmentService::class)->manifestRecapForShop();
            $pickupQueueRows = app(\App\Services\Vendor\VendorFulfillmentService::class)->pickupQueue(10);
            $returnPickupRows = \App\Models\OrderReturn::query()
                ->whereHas('order', fn ($q) => $q->where('shop_id', $vendorShopId))
                ->whereNotNull('pickup_scheduled_at')
                ->with(['order:id,order_number'])
                ->orderByDesc('pickup_scheduled_at')
                ->limit(10)
                ->get();
        } catch (\Throwable) {
            $manifestRecapRows = [];
        }
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-6">
            <x-admin.card title="Manifest AWB per kurir per hari" icon="truck">
                <p class="text-secondary small mb-2">Rekap kiriman toko ini yang sudah bermanifest. Batch print memakai tombol cetak label massal di atas.</p>
                @if (empty($manifestRecapRows))
                    <p class="text-secondary small mb-0">Belum ada manifest. Buat batch manifest dari kiriman yang dipilih.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Kurir</th><th>Tanggal</th><th>No. Manifest</th><th class="text-end">Kiriman</th></tr></thead>
                            <tbody>
                                @foreach (array_slice($manifestRecapRows, 0, 10) as $recap)
                                    <tr>
                                        <td>{{ $recap['courier'] }}</td>
                                        <td class="small">{{ $recap['date'] }}</td>
                                        <td><code>{{ $recap['manifest_no'] }}</code></td>
                                        <td class="text-end">{{ $recap['total'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.card>
        </div>
        <div class="col-12 col-xl-6">
            <x-admin.card title="Ambil di toko (click & collect)" icon="package">
                <p class="text-secondary small mb-2">Tanpa ongkir. Minta kode ambil 6 karakter kepada pelanggan, verifikasi sekali pakai, lalu serahkan barang.</p>
                @if ($pickupQueueRows->isEmpty())
                    <p class="text-secondary small mb-0">Tidak ada pengambilan menunggu.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Pesanan</th><th>Kode ambil</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($pickupQueueRows as $pickup)
                                    <tr>
                                        <td class="small">{{ $pickup->order?->order_number ?? ('#'.$pickup->order_id) }}</td>
                                        <td><code>{{ $pickup->pickup_code ?? '-' }}</code></td>
                                        <td>{{ $__status($pickup->pickup_verified_at ? 'Sudah diambil' : $pickup->status) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.card>
        </div>
        <div class="col-12">
            <x-admin.card title="Jemput retur terjadwal" icon="rotate-ccw">
                <p class="text-secondary small mb-2">Penjemputan retur oleh kurir beserta label retur. Penjadwalan dilakukan setelah retur disetujui.</p>
                @if ($returnPickupRows->isEmpty())
                    <p class="text-secondary small mb-0">Belum ada jadwal penjemputan retur.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>RMA</th><th>Pesanan</th><th>Jadwal jemput</th><th>Label retur</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($returnPickupRows as $retur)
                                    <tr>
                                        <td><code>{{ $retur->rma_number }}</code></td>
                                        <td class="small">{{ $retur->order?->order_number ?? '-' }}</td>
                                        <td class="small">{{ $retur->pickup_scheduled_at instanceof \DateTimeInterface ? $retur->pickup_scheduled_at->format('d/m/Y H:i') : (string) ($retur->pickup_scheduled_at ?? '-') }}</td>
                                        <td><code>{{ $retur->return_label_code ?? '-' }}</code></td>
                                        <td>{{ $__status($retur->pickup_status ?? '-') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.card>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl">
            <x-admin.stat label="Menunggu dikirim" :value="$total" icon="truck" color="warning" />
        </div>
        <div class="col-6 col-xl">
            <x-admin.stat label="Nilai antrean" :value="$value->toFloat()" icon="wallet" color="primary" />
        </div>
    </div>

    @if ($orders->isEmpty())
        <x-admin.card>
            <x-admin.empty-state
                icon="check-circle"
                title="Antrean kosong"
                text="Semua pesanan yang lunas sudah Anda kirim."
                action-label="Lihat semua pesanan"
                :action-url="route('vendor.orders.index')"
            />
        </x-admin.card>
    @else
        <div class="d-flex flex-column gap-3">
            @foreach ($orders as $order)
                <x-admin.card>
                    <div class="row g-3 align-items-start">
                        <div class="col-12 col-lg-7">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <input type="checkbox" name="order_ids[]" value="{{ $order->id }}" form="bulk-fulfillment-form" aria-label="Pilih {{ $order->order_number }}">
                                <a href="{{ route('vendor.orders.show', $order) }}" class="fw-semibold text-reset">
                                    {{ $order->order_number }}
                                </a>
                                {{ $__orderStatus($order->order_status) }}
                                {{ $__paymentStatus($order->payment_status) }}
                            </div>

                            <div class="text-secondary small mb-2">
                                {{ $order->customer?->name ?? 'Pelanggan' }} ·
                                {{ $order->items->sum('quantity') }} unit ·
                                {{ $order->created_at->format('d M Y H:i') }}
                            </div>

                            <ul class="list-unstyled small mb-2">
                                @foreach ($order->items->take(3) as $item)
                                    <li class="d-flex justify-content-between text-secondary">
                                        <span class="text-truncate me-2">{{ $item->quantity }}× {{ $item->product_name ?? ($item->product?->name ?? 'Produk') }}</span>
                                        <span class="text-nowrap">{{ Currency::format($item->sub_total) }}</span>
                                    </li>
                                @endforeach
                                @if ($order->items->count() > 3)
                                    <li class="text-secondary fst-italic">+{{ $order->items->count() - 3 }} produk lain</li>
                                @endif
                            </ul>

                            <div class="fw-semibold">Total {{ Currency::format($order->total) }}</div>
                        </div>

                        <div class="col-12 col-lg-5">
                            <form method="POST" action="{{ route('vendor.orders.ship', $order) }}" class="row g-2">
                                @csrf
                                <div class="col-12">
                                    <div class="alert alert-info py-2 px-3 small mb-0">
                                        Pengiriman dicatat sebagai jejak audit. Stok produk berkurang secara atomik
                                        dan pesanan hanya berpindah status bila resi valid.
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label small mb-1" for="courier-{{ $order->id }}">Kurir</label>
                                    <input class="form-control form-control-sm" id="courier-{{ $order->id }}" name="courier" required placeholder="mis. JNE">
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label small mb-1" for="service-{{ $order->id }}">Layanan</label>
                                    <input class="form-control form-control-sm" id="service-{{ $order->id }}" name="service" placeholder="mis. REG">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1" for="tracking-{{ $order->id }}">Nomor resi</label>
                                    <input class="form-control form-control-sm" id="tracking-{{ $order->id }}" name="tracking_number" required placeholder="Masukkan resi pelacakan">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1" for="weight-{{ $order->id }}">Berat (kg)</label>
                                    <input class="form-control form-control-sm" id="weight-{{ $order->id }}" name="weight" type="number" step="0.01" min="0">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1" for="cost-{{ $order->id }}">Biaya kirim</label>
                                    <input class="form-control form-control-sm" id="cost-{{ $order->id }}" name="cost" type="number" step="0.01" min="0" value="0">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-1" for="note-{{ $order->id }}">Catatan (opsional)</label>
                                    <input class="form-control form-control-sm" id="note-{{ $order->id }}" name="note" placeholder="mis. Dikemas dengan bubble wrap">
                                </div>
                                <div class="col-12 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1" data-confirm="Kirim pesanan {{ $order->order_number }}?">
                                        <x-admin.icon name="truck" :size="14" class="me-1" />
                                        <span>Kirim sekarang</span>
                                    </button>
                                    <a href="{{ route('vendor.orders.show', $order) }}" class="btn btn-outline-secondary btn-sm">Detail</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </x-admin.card>
            @endforeach
        </div>
    @endif
@endsection
