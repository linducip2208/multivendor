@extends('layouts.admin')

@section('title', 'Laporan Stok Produk')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Analytics', ['label' => 'Stok']]" />
@endsection

@section('content')
    <x-admin.page-header title="Laporan Stok" subtitle="Nilai stok, kondisi persediaan, dan pergerakan per gudang.">
        <x-slot:actions>
            <a href="{{ route('admin.inventory.index') }}" class="btn btn-primary btn-sm">
                <x-admin.icon name="layers" :size="14" /> Kelola Inventori
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
        @foreach ($report['kpis'] as $kpi)
            <div class="col-6 col-xl">
                <x-admin.stat :label="$kpi['label']" :value="$kpi['value']" :money="$kpi['money'] ?? false" />
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <x-admin.card title="Pergerakan Stok" subtitle="Unit masuk dan keluar per hari." icon="refresh">
                <x-admin.chart
                    id="stock-movement"
                    type="line"
                    :labels="$report['movement_series']['labels'] ?? []"
                    :data="[
                        ['label' => 'Masuk', 'data' => $report['movement_series']['in'] ?? []],
                        ['label' => 'Keluar', 'data' => $report['movement_series']['out'] ?? []],
                    ]"
                    :height="280"
                />
            </x-admin.card>
        </div>
        <div class="col-lg-5">
            <x-admin.card title="Stok per Gudang" icon="building" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Gudang</th>
                                <th scope="col" class="text-end">SKU</th>
                                <th scope="col" class="text-end">Unit</th>
                                <th scope="col" class="text-end">Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['by_warehouse'] as $row)
                                <tr>
                                    <td>
                                        {{ $row['name'] }}
                                        <small class="d-block text-secondary">{{ $row['code'] }} &middot; {{ $row['city'] }}</small>
                                    </td>
                                    <td class="text-end">{{ number_format($row['skus'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($row['units'], 0, ',', '.') }}</td>
                                    <td class="text-end fw-semibold">{{ \App\Support\Currency::format($row['value']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <x-admin.empty-state compact icon="building" title="Belum ada gudang" text="Tambahkan gudang pada halaman Inventori untuk melihat sebaran stok." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
    </div>

    <x-admin.card title="Rincian Produk" icon="package" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Produk</th>
                        <th scope="col">Toko</th>
                        <th scope="col" class="text-end">Stok</th>
                        <th scope="col" class="text-end">Batas Aman</th>
                        <th scope="col" class="text-end">Terjual</th>
                        <th scope="col" class="text-end">Perkiraan Cadangan</th>
                        <th scope="col" class="text-end">Nilai Stok</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['rows'] as $row)
                        <tr>
                            <td>
                                <a href="{{ $row['url'] }}">{{ $row['name'] }}</a>
                                <small class="d-block text-secondary">{{ $row['sku'] }}</small>
                            </td>
                            <td>{{ $row['shop'] }}</td>
                            <td class="text-end">
                                <x-admin.badge
                                    :text="number_format($row['stock'], 0, ',', '.')"
                                    :color="match($row['state']) { 'out_of_stock' => 'danger', 'low_stock' => 'warning', default => 'success' }"
                                    pill
                                />
                            </td>
                            <td class="text-end">{{ number_format($row['threshold'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($row['sold'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ $row['cover_days'] === null ? '-' : $row['cover_days'].' hari' }}</td>
                            <td class="text-end fw-semibold">{{ \App\Support\Currency::format($row['value']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-admin.empty-state icon="package" title="Belum ada produk" text="Tidak ada produk yang cocok dengan filter ini." />
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
