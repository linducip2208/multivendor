@extends('layouts.admin')

@section('title', 'Detail Pengiriman '.$shipment['order_number'])

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Fulfillment', ['label' => 'Pengiriman', 'href' => route('admin.shipments.index')], ['label' => $shipment['order_number']]]" />
@endsection

@section('content')
    <x-admin.page-header :title="$shipment['order_number']" :subtitle="$shipment['courier'].' · '.$shipment['service']">
        <x-slot:actions>
            <form method="POST" action="{{ route('admin.shipments.track', $shipment['id']) }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <x-admin.icon name="refresh" :size="14" /> Perbarui Tracking
                </button>
            </form>
            <a href="{{ route('admin.shipments.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Ongkir" :value="$shipment['cost']" money icon="truck" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total Pesanan" :value="$order['total']" money icon="cash" color="success" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.badge :text="strtoupper($shipment['status'])" :color="match($shipment['status']) { 'delivered' => 'success', 'in_transit' => 'warning', 'failed' => 'danger', default => 'info' }" pill class="d-inline-flex" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Riwayat Tracking" :value="count($history)" icon="activity" color="secondary" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <x-admin.card title="Rincian Kiriman" icon="info" class="mb-3">
                <dl class="row small mb-0">
                    <dt class="col-5 text-secondary">Nomor resi</dt>
                    <dd class="col-7 text-end"><code>{{ $shipment['tracking_number'] !== '' ? $shipment['tracking_number'] : 'Belum ada' }}</code></dd>
                    <dt class="col-5 text-secondary">Kurir</dt>
                    <dd class="col-7 text-end">{{ $shipment['courier'] }}</dd>
                    <dt class="col-5 text-secondary">Layanan</dt>
                    <dd class="col-7 text-end">{{ $shipment['service'] }}</dd>
                    <dt class="col-5 text-secondary">Berat</dt>
                    <dd class="col-7 text-end">{{ $shipment['weight'] !== null ? $shipment['weight'].' gram' : '-' }}</dd>
                    <dt class="col-5 text-secondary">Provider terhubung</dt>
                    <dd class="col-7 text-end">{{ $shipment['has_provider'] ? 'Ya' : 'Tidak' }}</dd>
                    <dt class="col-5 text-secondary">Label</dt>
                    <dd class="col-7 text-end">
                        @if ($shipment['label_url'] !== '')
                            <a href="{{ $shipment['label_url'] }}" target="_blank" rel="noopener noreferrer">Unduh label</a>
                        @else
                            -
                        @endif
                    </dd>
                </dl>
            </x-admin.card>

            <x-admin.card title="Penerima" icon="user" class="mb-3">
                <dl class="row small mb-0">
                    <dt class="col-4 text-secondary">Nama</dt>
                    <dd class="col-8 text-end">{{ $order['customer'] }}</dd>
                    <dt class="col-4 text-secondary">Email</dt>
                    <dd class="col-8 text-end text-break">{{ $order['email'] }}</dd>
                    <dt class="col-4 text-secondary">Telepon</dt>
                    <dd class="col-8 text-end">{{ $order['phone'] !== '' ? $order['phone'] : '-' }}</dd>
                    <dt class="col-4 text-secondary">Status pesanan</dt>
                    <dd class="col-8 text-end">{{ \Illuminate\Support\Str::headline($order['order_status']) }}</dd>
                    <dt class="col-4 text-secondary">Status bayar</dt>
                    <dd class="col-8 text-end">{{ \Illuminate\Support\Str::headline($order['payment_status']) }}</dd>
                </dl>
                @if ($order['address'] !== [])
                    <address class="small text-secondary mt-3 mb-0">
                        @foreach ($order['address'] as $line)
                            {{ $line }}<br>
                        @endforeach
                    </address>
                @endif
            </x-admin.card>

            <x-admin.card title="Riwayat Tracking" icon="activity">
                <x-admin.timeline
                    :items="collect($history)->map(fn (array $step): array => [
                        'title' => $step['description'],
                        'meta' => $step['at'],
                        'body' => null,
                        'state' => 'done',
                    ])->all()"
                />
            </x-admin.card>
        </div>

        <div class="col-lg-7">
            <x-admin.card title="Item Pesanan" icon="package" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Produk</th>
                                <th scope="col" class="text-end">Jumlah</th>
                                <th scope="col" class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($items as $item)
                                <tr>
                                    <td>
                                        {{ $item['name'] }}
                                        <small class="d-block text-secondary">{{ $item['sku'] }}</small>
                                    </td>
                                    <td class="text-end">{{ number_format($item['quantity'], 0, ',', '.') }}</td>
                                    <td class="text-end fw-semibold">{{ $item['sub_total_formatted'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        <x-admin.empty-state compact icon="package" title="Item tidak ditemukan" text="Detail item pesanan tidak dapat dimuat." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
    </div>
@endsection
