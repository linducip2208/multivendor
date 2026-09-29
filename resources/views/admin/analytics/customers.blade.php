@extends('layouts.admin')

@section('title', 'Analitik Pelanggan')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Analytics', ['label' => 'Pelanggan']]" />
@endsection

@section('content')
    <x-admin.page-header title="Pelanggan" subtitle="Akuisisi, retensi, dan nilai pelanggan berdasarkan transaksi nyata.">
        <x-slot:actions>
            <a href="{{ route('admin.export.customers', request()->query()) }}" class="btn btn-outline-secondary btn-sm">
                <x-admin.icon name="download" :size="14" /> Ekspor CSV
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card mb-3">
        <div class="card-body">
            @include('admin.partials.date-range', ['range' => $range])
        </div>
    </div>

    <x-admin.tabs :tabs="$tabs" class="mb-3" />

    <div class="row g-3 mb-3">
        @foreach ($report['kpis'] as $key => $kpi)
            <div class="col-6 col-xl-4">
                <x-admin.stat
                    :label="$kpi['label']"
                    :value="$kpi['value']"
                    :money="$kpi['money'] ?? false"
                    :hint="$kpi['hint'] ?? null"
                    :trend="$kpi['trend']['direction'] ?? null"
                    :trend-label="isset($kpi['trend']['value']) ? (($kpi['trend']['direction'] === 'up' ? '+' : '').number_format((float) $kpi['trend']['value'], 1, ',', '.').'%') : null"
                    :icon="match($key) { 'buyers' => 'users', 'new' => 'user-plus', 'returning' => 'refresh', 'retention' => 'target', 'registered' => 'user-check', default => 'calculator' }"
                    :color="match($key) { 'retention' => 'success', 'new' => 'info', default => 'primary' }"
                />
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <x-admin.card title="Pembeli per Hari" subtitle="Pembeli unik dan pembeli baru." icon="users">
                <x-admin.chart
                    id="buyer-trend"
                    type="line"
                    :labels="$report['series']['labels'] ?? []"
                    :data="[
                        ['label' => 'Pembeli', 'data' => $report['series']['buyers'] ?? []],
                        ['label' => 'Pembeli Baru', 'data' => $report['series']['new_buyers'] ?? []],
                    ]"
                    :height="280"
                />
            </x-admin.card>
        </div>
        <div class="col-lg-5">
            <x-admin.card title="Distribusi Frekuensi" subtitle="Jumlah pesanan per pelanggan." icon="bar-chart">
                <x-admin.chart
                    type="bar"
                    :labels="array_column($report['tiers'], 'label')"
                    :data="[['label' => 'Pelanggan', 'data' => array_column($report['tiers'], 'count')]]"
                    :height="280"
                />
            </x-admin.card>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <x-admin.card title="Wilayah Pengiriman" subtitle="10 provinsi teratas berdasarkan nilai pesanan." icon="map-pin" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Provinsi</th>
                                <th scope="col" class="text-end">Pelanggan</th>
                                <th scope="col" class="text-end">Pesanan</th>
                                <th scope="col" class="text-end">Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['locations'] as $location)
                                <tr>
                                    <td>{{ $location['province'] }}</td>
                                    <td class="text-end">{{ number_format($location['customers'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($location['orders'], 0, ',', '.') }}</td>
                                    <td class="text-end fw-semibold">{{ \App\Support\Currency::format($location['revenue']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <x-admin.empty-state compact icon="map-pin" title="Belum ada data wilayah" text="Alamat pengiriman belum tercatat pada periode ini." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>

        <div class="col-lg-6">
            <x-admin.card title="Pelanggan dengan Nilai Tertinggi" icon="award" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Pelanggan</th>
                                <th scope="col" class="text-end">Pesanan</th>
                                <th scope="col" class="text-end">Belanja</th>
                                <th scope="col">Pesanan Terakhir</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows['rows'] as $row)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.customers.360', $row['customer_id']) }}">{{ $row['name'] }}</a>
                                        <small class="d-block text-secondary">{{ $row['email'] }}</small>
                                    </td>
                                    <td class="text-end">{{ number_format($row['orders'], 0, ',', '.') }}</td>
                                    <td class="text-end fw-semibold">{{ \App\Support\Currency::format($row['spend']) }}</td>
                                    <td class="text-nowrap">{{ \Illuminate\Support\Str::limit((string) $row['last_order'], 16) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <x-admin.empty-state compact icon="users" title="Belum ada pembeli" text="Tidak ada pelanggan yang melakukan pesanan pada periode ini." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
    </div>

    <x-admin.pagination
        :paginator="\App\Support\AdminPaginator::fromArray($report, $report['rows'] ? count($report['rows']) : 0, 20, max(1, (int) request()->query('page', 1)))"
        size="sm"
    />
@endsection
