@extends('layouts.admin')

@section('title', 'Pengiriman')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Fulfillment', ['label' => 'Pengiriman']]" />
@endsection

@section('content')
    <x-admin.page-header title="Pengiriman" subtitle="Status kiriman per nomor resi.">
        <x-slot:actions>
            <a href="{{ route('admin.couriers.index') }}" class="btn btn-outline-secondary btn-sm">
                <x-admin.icon name="globe" :size="14" /> Provider Kurir
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total" :value="$counts['all']" icon="truck" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Dalam Perjalanan" :value="$counts['in_transit']" icon="refresh" color="warning" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Terkirim" :value="$counts['shipped']" icon="package" color="info" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Selesai" :value="$counts['delivered']" icon="check" color="success" />
        </div>
    </div>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.shipments.index')"
            :filters="[
                ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Nomor resi atau nomor pesanan'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => array_merge(
                    ['' => 'Semua status'],
                    array_filter(array_combine(array_keys($counts), $counts), fn ($value, $key): bool => $key !== 'all', ARRAY_FILTER_USE_BOTH),
                )],
            ]"
        />
    </x-admin.card>

    <x-admin.card title="Daftar Pengiriman" icon="truck" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Pesanan</th>
                        <th scope="col">Kurir</th>
                        <th scope="col">Nomor Resi</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-end">Ongkir</th>
                        <th scope="col">Dikirim</th>
                        <th scope="col">Diterima</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td><a href="{{ route('admin.orders.show', $row['order_id']) }}">{{ $row['order_number'] }}</a></td>
                            <td>
                                {{ $row['courier'] }}
                                <small class="d-block text-secondary">{{ $row['service'] }}</small>
                            </td>
                            <td><code class="small">{{ $row['tracking_number'] }}</code></td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="\Illuminate\Support\Str::headline($row['status'])"
                                    :color="match($row['status']) { 'delivered' => 'success', 'in_transit' => 'warning', 'shipped' => 'info', 'failed' => 'danger', 'returned' => 'secondary', default => 'secondary' }"
                                    pill
                                />
                            </td>
                            <td class="text-end">{{ $row['cost_formatted'] }}</td>
                            <td class="text-nowrap">{{ $row['shipped_at'] !== '' ? $row['shipped_at'] : '-' }}</td>
                            <td class="text-nowrap">{{ $row['delivered_at'] !== '' ? $row['delivered_at'] : '-' }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.shipments.show', $row['id']) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-admin.empty-state icon="truck" title="Belum ada pengiriman" text="Data pengiriman akan muncul setelah pesanan diserahkan ke kurir." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <div class="mt-3">
        <x-admin.pagination :paginator="\App\Support\AdminPaginator::fromArray($pagination, $pagination['total'], $pagination['per_page'], $pagination['current_page'])" size="sm" />
    </div>
@endsection
