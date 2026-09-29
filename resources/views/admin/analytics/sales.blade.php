@extends('layouts.admin')

@section('title', 'Analitik Penjualan')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Analitik', ['label' => 'Penjualan']]" />
@endsection

@section('content')
    <x-admin.page-header title="Penjualan" subtitle="Tren, komposisi, dan daftar pesanan pada periode terpilih.">
        <x-slot:actions>
            <x-admin.dropdown label="Unduh" icon="download" variant="outline-secondary" size="sm">
                <x-admin.dropdown-item :href="route('admin.export.orders', request()->query())" icon="file-bar">Pesanan (CSV)</x-admin.dropdown-item>
                <x-admin.dropdown-item :href="route('admin.export.transactions', request()->query())" icon="receipt">Transaksi (CSV)</x-admin.dropdown-item>
            </x-admin.dropdown>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card mb-3">
        <div class="card-body">
            @include('admin.partials.date-range', ['range' => $range])
        </div>
    </div>

    <x-admin.tabs :tabs="$tabs" class="mb-3" />

    <div class="row g-3 mb-3">
        @foreach ($report['kpis'] as $kpi)
            <div class="col-6 col-xl">
                <x-admin.stat
                    :label="$kpi['label']"
                    :value="$kpi['value']"
                    :money="$kpi['money'] ?? false"
                    :trend="$kpi['trend']['direction'] ?? null"
                    :trend-label="isset($kpi['trend']['value']) ? (($kpi['trend']['direction'] === 'up' ? '+' : '').number_format((float) $kpi['trend']['value'], 1, ',', '.').'%') : null"
                />
            </div>
        @endforeach
    </div>

    <x-admin.card title="Tren Penjualan" subtitle="GMV, pesanan, dan nilai refund per hari." icon="shopping-cart" class="mb-3">
        <x-admin.chart
            id="sales-trend"
            type="line"
            :labels="$report['series']['labels'] ?? []"
            :data="[
                ['label' => 'GMV', 'data' => $report['series']['gmv'] ?? []],
                ['label' => 'Pesanan', 'data' => $report['series']['orders'] ?? []],
                ['label' => 'Refund', 'data' => $report['series']['refunds'] ?? []],
            ]"
            :height="300"
        />
    </x-admin.card>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <x-admin.card title="Metode Pembayaran" icon="credit-card">
                <x-admin.chart
                    type="doughnut"
                    :labels="$report['payment_mix']['labels'] ?? []"
                    :data="[['label' => 'Nilai', 'data' => $report['payment_mix']['values'] ?? []]]"
                    :height="240"
                />
            </x-admin.card>
        </div>
        <div class="col-lg-4">
            <x-admin.card title="Status Pesanan" icon="list-ordered">
                <x-admin.chart
                    type="doughnut"
                    :labels="$report['status_mix']['labels'] ?? []"
                    :data="[['label' => 'Nilai', 'data' => $report['status_mix']['values'] ?? []]]"
                    :height="240"
                />
            </x-admin.card>
        </div>
        <div class="col-lg-4">
            <x-admin.card title="Toko Teratas" subtitle="10 toko dengan nilai tertinggi." icon="store">
                <x-admin.chart
                    type="bar"
                    horizontal
                    :labels="$report['shop_mix']['labels'] ?? []"
                    :data="[['label' => 'Omzet', 'data' => $report['shop_mix']['values'] ?? []]]"
                    :height="240"
                />
            </x-admin.card>
        </div>
    </div>

    <x-admin.card
        title="Daftar Pesanan"
        :subtitle="number_format($orders['total'], 0, ',', '.').' pesanan dalam periode ini.'"
        icon="shopping-bag"
        flush
    >
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover" id="sales-orders">
                <caption class="caption-top text-secondary small">
                    Periode {{ $range->from->toDateString() }} sampai {{ $range->to->toDateString() }}
                </caption>
                <thead>
                    <tr>
                        <th scope="col">Nomor</th>
                        <th scope="col">Pelanggan</th>
                        <th scope="col">Toko</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-center">Pembayaran</th>
                        <th scope="col" class="text-end">Total</th>
                        <th scope="col">Dibuat</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders['rows'] as $row)
                        <tr>
                            <td><a href="{{ $row['url'] }}">{{ $row['order_number'] }}</a></td>
                            <td>{{ $row['customer'] }}</td>
                            <td>{{ $row['shop'] }}</td>
                            <td class="text-center">
                                <x-admin.badge :text="$row['order_status_label']" :color="$row['order_status_badge']" pill />
                            </td>
                            <td class="text-center">
                                <x-admin.badge :text="$row['payment_status_label']" :color="$row['payment_status_badge']" pill />
                            </td>
                            <td class="text-end fw-semibold">{{ $row['total_formatted'] }}</td>
                            <td class="text-nowrap">{{ $row['created_at'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-admin.empty-state
                                    icon="inbox"
                                    title="Belum ada pesanan"
                                    text="Tidak ada pesanan yang tercatat pada periode ini."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <div class="mt-3">
        <x-admin.pagination
            :paginator="\App\Support\AdminPaginator::fromArray($orders, $orders['total'], $orders['per_page'], $orders['current_page'])"
            size="sm"
        />
    </div>
@endsection
