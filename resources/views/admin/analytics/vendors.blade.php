@extends('layouts.admin')

@section('title', 'Analitik Vendor')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Analitik', ['label' => 'Vendor']]" />
@endsection

@section('content')
    <x-admin.page-header title="Performa Vendor" subtitle="Omzet, komisi, dan pergeseran performa toko pada periode terpilih." />

    <div class="card mb-3">
        <div class="card-body">
            @include('admin.partials.date-range', ['range' => $range])
        </div>
    </div>

    <x-admin.tabs :tabs="$tabs" class="mb-3" />

    <div class="row g-3 mb-3">
        @foreach ($report['kpis'] as $key => $kpi)
            <div class="col-6 col-xl">
                <x-admin.stat
                    :label="$kpi['label']"
                    :value="$kpi['value']"
                    :money="$kpi['money'] ?? false"
                    :hint="$kpi['hint'] ?? null"
                />
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <x-admin.card title="Omzet Mitra" subtitle="Nilai transaksi per hari." icon="store">
                <x-admin.chart
                    id="vendor-trend"
                    type="line"
                    :labels="$report['series']['labels'] ?? []"
                    :data="[['label' => 'Omzet', 'data' => $report['series']['revenue'] ?? []]]"
                    :height="280"
                />
            </x-admin.card>
        </div>
        <div class="col-lg-5">
            <x-admin.card title="Konsentrasi Omzet" subtitle="Berapa porsi omzet tiap kelompok toko." icon="bar-chart">
                <x-admin.chart
                    type="doughnut"
                    :labels="array_column($report['tiers'], 'label')"
                    :data="[['label' => 'Omzet', 'data' => array_column($report['tiers'], 'revenue')]]"
                    :height="280"
                />
            </x-admin.card>
        </div>
    </div>

    <x-admin.card title="Daftar Toko" icon="users" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Toko</th>
                        <th scope="col" class="text-center">Produk</th>
                        <th scope="col" class="text-center">Peringkat</th>
                        <th scope="col" class="text-end">Pesanan</th>
                        <th scope="col" class="text-end">Omzet</th>
                        <th scope="col" class="text-end">Komisi</th>
                        <th scope="col" class="text-end">Hak Vendor</th>
                        <th scope="col" class="text-end">Tren</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['rows'] as $row)
                        <tr>
                            <td>
                                <a href="{{ $row['url'] }}">{{ $row['name'] }}</a>
                                <small class="d-block text-secondary">{{ $row['city'] }}</small>
                            </td>
                            <td class="text-end">{{ number_format($row['products'], 0, ',', '.') }}</td>
                            <td class="text-center">
                                <x-admin.badge :text="number_format($row['rating'], 1, ',', '.').' / 5'" :color="$row['rating'] >= 4 ? 'success' : ($row['rating'] >= 3 ? 'warning' : 'danger')" pill />
                            </td>
                            <td class="text-end">{{ number_format($row['orders'], 0, ',', '.') }}</td>
                            <td class="text-end fw-semibold">{{ \App\Support\Currency::format($row['revenue']) }}</td>
                            <td class="text-end">{{ \App\Support\Currency::format($row['commission']) }}</td>
                            <td class="text-end">{{ \App\Support\Currency::format($row['payout']) }}</td>
                            <td class="text-end">
                                <x-admin.badge
                                    :text="($row['trend']['direction'] === 'up' ? '+' : ($row['trend']['direction'] === 'down' ? '-' : '')).number_format(abs((float) $row['trend']['value']), 1, ',', '.').'%'"
                                    :color="$row['trend']['direction'] === 'up' ? 'success' : ($row['trend']['direction'] === 'down' ? 'danger' : 'secondary')"
                                    pill
                                />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-admin.empty-state icon="store" title="Belum ada toko aktif" text="Tidak ada toko dengan penjualan pada periode ini." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <div class="mt-3">
        <x-admin.pagination :paginator="\App\Support\AdminPaginator::fromArray($report['pagination'], $report['pagination']['total'], $report['pagination']['per_page'], $report['pagination']['current_page'])" size="sm" />
    </div>
@endsection
