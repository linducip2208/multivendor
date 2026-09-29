@extends('layouts.admin')

@section('title', 'Detail Kurir '.$name)

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Fulfillment', ['label' => 'Kurir', 'href' => route('admin.couriers.index')], ['label' => $name]]" />
@endsection

@section('content')
    <x-admin.page-header :title="$name" :subtitle="$api_format.' · '.$host">
        <x-slot:actions>
            <a href="{{ route('admin.providers.index') }}" class="btn btn-outline-secondary btn-sm">Kelola Provider</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total Pengiriman" :value="number_format($deliveries, 0, ',', '.')" icon="truck" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Status" :value="$is_active ? 'Aktif' : 'Nonaktif'" icon="check" :color="$is_active ? 'success' : 'secondary'" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Kredensial" :value="$configured ? 'Terpasang' : 'Belum'" icon="lock" :color="$configured ? 'success' : 'warning'" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Base URL" :value="$host" icon="globe" color="info" />
        </div>
    </div>

    <x-admin.card title="Pengiriman Terbaru" icon="truck" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Pesanan</th>
                        <th scope="col">Nomor Resi</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col">Dikirim</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recent as $shipment)
                        <tr>
                            <td><a href="{{ route('admin.orders.show', $shipment['order_id']) }}">{{ $shipment['order_number'] }}</a></td>
                            <td><code class="small">{{ $shipment['tracking_number'] }}</code></td>
                            <td class="text-center">
                                <x-admin.badge :text="\Illuminate\Support\Str::headline($shipment['status'])" :color="match($shipment['status']) { 'delivered' => 'success', 'in_transit' => 'warning', default => 'info' }" pill />
                            </td>
                            <td class="text-nowrap">{{ $shipment['shipped_at'] !== '' ? $shipment['shipped_at'] : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-admin.empty-state compact icon="truck" title="Belum ada pengiriman" text="Provider ini belum mengirim kiriman apa pun." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>
@endsection
