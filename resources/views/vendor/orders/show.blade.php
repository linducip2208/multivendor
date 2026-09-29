@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Pesanan '.$order->order_number)
@section('subtitle', ($order->customer?->name ?? 'Pelanggan').' · '.$order->created_at->format('d M Y H:i'))

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Pesanan', 'href' => route('vendor.orders.index')],
    ['label' => $order->order_number],
])

@section('actions')
    <a href="{{ route('vendor.orders.index') }}" class="btn btn-outline-secondary">
        <x-admin.icon name="arrow-left" :size="16" class="me-1" />
        <span>Daftar pesanan</span>
    </a>
    @if (in_array($order->order_status, \App\Services\Vendor\OrderEditService::editableStatuses(), true))
        <a href="{{ route('vendor.orders.edit', $order) }}" class="btn btn-outline-secondary">
            <x-admin.icon name="edit" :size="16" class="me-1" />
            <span>Ubah pesanan</span>
        </a>
    @endif
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-admin.card title="Item pesanan" icon="package" :padding="false" flush>
                <x-admin.table>
                    <x-slot:table>
                        \App\Support\TableBuilder::make()
                            ->columns([
                                'product' => ['label' => 'Produk', 'width' => '40%'],
                                'quantity' => ['label' => 'Qty', 'align' => 'end'],
                                'price' => ['label' => 'Harga', 'align' => 'end'],
                                'discount' => ['label' => 'Diskon', 'align' => 'end'],
                                'sub_total' => ['label' => 'Subtotal', 'align' => 'end'],
                            ])
                            ->rows(
                                $order->items->map(fn ($item) => [
                                    'product' => '<span class="fw-medium d-block text-truncate">'.e($item->product?->name ?? 'Produk tidak tersedia').'</span>'
                                        .($item->variant?->name ? '<span class="text-secondary small">'.e($item->variant?->name).'</span>' : ''),
                                    'quantity' => e(Currency::number($item->quantity)),
                                    'price' => '<span class="text-nowrap">'.e(Currency::format($item->price)).'</span>',
                                    'discount' => (float) $item->discount > 0 ? '-'.e(Currency::format($item->discount)) : '—',
                                    'sub_total' => '<span class="fw-medium text-nowrap">'.e(Currency::format($item->sub_total)).'</span>',
                                ])->all()
                            )
                    </x-slot:table>

                    <x-slot:tfoot>
                        <tr>
                            <td colspan="4" class="text-end text-secondary">Subtotal</td>
                            <td class="text-end">{{ Currency::format($order->sub_total) }}</td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-end text-secondary">Pajak</td>
                            <td class="text-end">{{ Currency::format($order->tax) }}</td>
                        </tr>
                        @if ((float) $order->discount > 0)
                            <tr>
                                <td colspan="4" class="text-end text-secondary">Diskon</td>
                                <td class="text-end">-{{ Currency::format($order->discount) }}</td>
                            </tr>
                        @endif
                        @if ((float) $order->coupon_discount > 0)
                            <tr>
                                <td colspan="4" class="text-end text-secondary">Kupon {{ $order->coupon_code ? '('.$order->coupon_code.')' : '' }}</td>
                                <td class="text-end">-{{ Currency::format($order->coupon_discount) }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td colspan="4" class="text-end text-secondary">Ongkir</td>
                            <td class="text-end">{{ Currency::format($order->shipping_cost) }}</td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-end fw-semibold">Total</td>
                            <td class="text-end fw-semibold">{{ Currency::format($order->total) }}</td>
                        </tr>
                    </x-slot:tfoot>
                </x-admin.table>
            </x-admin.card>

            @if ($order->shipments->isNotEmpty())
                <x-admin.card title="Riwayat pengiriman" icon="truck" class="mt-3" :padding="false">
                    <x-admin.table dense>
                        <x-slot:table>
                            \App\Support\TableBuilder::make()
                                ->columns([
                                    'courier' => ['label' => 'Kurir'],
                                    'tracking' => ['label' => 'Resi'],
                                    'cost' => ['label' => 'Biaya', 'align' => 'end'],
                                    'status' => ['label' => 'Status'],
                                    'shipped' => ['label' => 'Dikirim', 'align' => 'end'],
                                ])
                                ->rows(
                                    $order->shipments->map(fn ($shipment) => [
                                        'courier' => '<span class="fw-medium">'.e($shipment->courier).'</span>'.($shipment->service ? '<span class="text-secondary small d-block">'.e($shipment->service).'</span>' : ''),
                                        'tracking' => '<span class="font-monospace small">'.e($shipment->tracking_number ?: '—').'</span>',
                                        'cost' => '<span class="text-nowrap">'.e(Currency::format($shipment->cost)).'</span>',
                                        'status' => $__status($shipment->status),
                                        'shipped' => '<span class="text-secondary small">'.e($shipment->shipped_at?->format('d/m/Y H:i') ?? '—').'</span>',
                                    ])->all()
                                )
                                ->empty('Belum ada catatan pengiriman.')
                        </x-slot:table>
                    </x-admin.table>
                </x-admin.card>
            @endif

            <x-admin.card title="Riwayat status" icon="history" class="mt-3">
                <x-admin.activity-feed
                    :items="$order->statusHistory->map(fn ($history) => [
                        'actor' => $history->changedBy?->name ?? 'Sistem',
                        'action' => $history->note ?: (\Illuminate\Support\Str::headline((string) $history->status)),
                        'at' => $history->created_at->format('d/m/Y H:i'),
                        'icon' => 'activity',
                    ])->all()"
                    empty="Belum ada riwayat status."
                />
            </x-admin.card>
        </div>

        <div class="col-12 col-xl-4">
            <x-admin.card title="Informasi pesanan" icon="info">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-secondary fw-normal">Status</dt>
                    <dd class="col-7 text-end">{{ $__orderStatus($order->order_status) }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Pembayaran</dt>
                    <dd class="col-7 text-end">{{ $__paymentStatus($order->payment_status) }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Pemenuhan</dt>
                    <dd class="col-7 text-end">{{ $__status($order->fulfillment_status) }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Sumber</dt>
                    <dd class="col-7 text-end text-uppercase">{{ $order->source ?? 'web' }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Pelanggan</dt>
                    <dd class="col-7 text-end">
                        @if ($order->customer)
                            <a href="{{ route('vendor.customers.show', $order->customer_id) }}">{{ $order->customer->name }}</a>
                            <div class="text-secondary">{{ $order->customer->email }}</div>
                        @else
                            —
                        @endif
                    </dd>
                    <dt class="col-5 text-secondary fw-normal">Resi</dt>
                    <dd class="col-7 text-end font-monospace">{{ $order->shipping_tracking_id ?: '—' }}</dd>
                </dl>

                @if (! empty($order->shipping_address))
                    <hr class="my-3" />
                    <div class="small">
                        <div class="fw-semibold mb-1">Alamat kirim</div>
                        <div class="text-secondary">{{ $order->shipping_address['address'] ?? '—' }}</div>
                        <div class="text-secondary">
                            {{ implode(', ', array_filter([$order->shipping_address['city'] ?? null, $order->shipping_address['state'] ?? null, $order->shipping_address['postcode'] ?? null])) }}
                        </div>
                    </div>
                @endif

                @if ($order->note)
                    <hr class="my-3" />
                    <div class="small">
                        <div class="fw-semibold mb-1">Catatan pelanggan</div>
                        <div class="text-secondary">{{ $order->note }}</div>
                    </div>
                @endif
            </x-admin.card>

            @if ($shippable)
                <x-admin.card title="Kirim pesanan" icon="truck" class="mt-3">
                    <form method="POST" action="{{ route('vendor.orders.ship', $order) }}" data-confirm="Kirim pesanan {{ $order->order_number }}?">
                        @csrf

                        <div class="row g-2">
                            <div class="col-12 col-sm-6">
                                <x-admin.form-field name="courier" label="Kurir" required placeholder="mis. JNE" />
                            </div>
                            <div class="col-12 col-sm-6">
                                <x-admin.form-field name="service" label="Layanan" placeholder="mis. REG" />
                            </div>
                            <div class="col-12">
                                <x-admin.form-field name="tracking_number" label="Nomor resi" required placeholder="Masukkan resi pelacakan" />
                            </div>
                            <div class="col-6">
                                <x-admin.form-field name="weight" label="Berat (kg)" type="number" :min="0" :step="0.01" />
                            </div>
                            <div class="col-6">
                                <x-admin.form-field name="cost" label="Biaya kirim" type="number" :min="0" :step="0.01" :value="0" />
                            </div>
                            <div class="col-12">
                                <x-admin.form-field name="note" label="Catatan" />
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <x-admin.icon name="truck" :size="16" class="me-1" />
                            <span>Tandai dikirim</span>
                        </button>
                    </form>
                </x-admin.card>
            @endif

            @if (in_array($order->order_status, ['pending', 'paid', 'confirmed', 'processing'], true))
                <x-admin.card title="Ubah status" icon="refresh" class="mt-3">
                    <form method="POST" action="{{ route('vendor.orders.update-status', $order) }}">
                        @csrf
                        @method('PUT')

                        <x-admin.form-field
                            name="status"
                            label="Status baru"
                            type="select"
                            required
                            :options="collect($transitions)->mapWithKeys(fn ($transition) => [$transition => \Illuminate\Support\Str::headline($transition)])->all()"
                        />
                        <x-admin.form-field name="note" label="Catatan" type="textarea" :rows="2" />
                        <x-admin.form-field name="reason" label="Alasan (untuk pembatalan)" type="textarea" :rows="2" />

                        <button type="submit" class="btn btn-outline-primary w-100">
                            <x-admin.icon name="check" :size="16" class="me-1" />
                            <span>Perbarui status</span>
                        </button>
                    </form>
                </x-admin.card>
            @endif
        </div>
    </div>
@endsection
