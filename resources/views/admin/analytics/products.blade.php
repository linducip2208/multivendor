@extends('layouts.admin')

@section('title', 'Analitik Produk')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Analytics', ['label' => 'Produk']]" />
@endsection

@section('content')
    <x-admin.page-header title="Performa Produk" subtitle="Produk terlaris, sebaran kategori, dan kesehatan katalog.">
        <x-slot:actions>
            <a href="{{ route('admin.export.products', request()->query()) }}" class="btn btn-outline-secondary btn-sm">
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
                    :icon="match($key) { 'products' => 'package', 'revenue' => 'cash', 'units' => 'shopping-cart', 'out_of_stock' => 'x-circle', 'low_stock' => 'alert-triangle', default => 'target' }"
                    :color="match($key) { 'out_of_stock' => 'danger', 'low_stock' => 'warning', 'revenue' => 'success', default => 'primary' }"
                />
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <x-admin.card title="10 Produk Teratas" subtitle="Berdasarkan omzet periode ini." icon="trophy">
                <x-admin.chart
                    id="top-products"
                    type="bar"
                    horizontal
                    :labels="array_map(fn (string $name): string => \Illuminate\Support\Str::limit($name, 28), $report['top_labels'] ?? [])"
                    :data="[['label' => 'Omzet', 'data' => $report['top_revenue'] ?? []]]"
                    :height="320"
                />
            </x-admin.card>
        </div>
        <div class="col-lg-5">
            <x-admin.card title="Sebaran Rating" subtitle="Jumlah produk aktif per bucket bintang." icon="star">
                <x-admin.chart
                    type="bar"
                    :labels="array_map(fn (array $row): string => $row['bucket'].' ★', $report['ratings'])"
                    :data="[['label' => 'Produk', 'data' => array_column($report['ratings'], 'count')]]"
                    :height="320"
                />
            </x-admin.card>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <x-admin.card title="Kategori Teratas" icon="category" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Kategori</th>
                                <th scope="col" class="text-end">Produk</th>
                                <th scope="col" class="text-end">Omzet</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['categories'] as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td class="text-end">{{ number_format($row['products'], 0, ',', '.') }}</td>
                                    <td class="text-end fw-semibold">{{ \App\Support\Currency::format($row['revenue']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3"><x-admin.empty-state compact icon="category" title="Belum ada data kategori" text="Belum ada penjualan berkategori pada periode ini." /></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
        <div class="col-lg-6">
            <x-admin.card title="Brand Teratas" icon="award" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Brand</th>
                                <th scope="col" class="text-end">Produk</th>
                                <th scope="col" class="text-end">Omzet</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['brands'] as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td class="text-end">{{ number_format($row['products'], 0, ',', '.') }}</td>
                                    <td class="text-end fw-semibold">{{ \App\Support\Currency::format($row['revenue']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3"><x-admin.empty-state compact icon="award" title="Belum ada data brand" text="Belum ada penjualan berbrand pada periode ini." /></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
    </div>

    <x-admin.card title="Semua Produk" icon="package" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Produk</th>
                        <th scope="col">Toko</th>
                        <th scope="col">Kategori</th>
                        <th scope="col" class="text-end">Harga</th>
                        <th scope="col" class="text-end">Stok</th>
                        <th scope="col" class="text-end">Unit Terjual</th>
                        <th scope="col" class="text-end">Omzet</th>
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
                            <td>{{ $row['category'] }}</td>
                            <td class="text-end">{{ \App\Support\Currency::format($row['price']) }}</td>
                            <td class="text-end">
                                <x-admin.badge
                                    :text="number_format($row['stock'], 0, ',', '.')"
                                    :color="$row['stock'] <= 0 ? 'danger' : 'secondary'"
                                    pill
                                />
                            </td>
                            <td class="text-end">{{ number_format($row['units'], 0, ',', '.') }}</td>
                            <td class="text-end fw-semibold">{{ \App\Support\Currency::format($row['revenue']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-admin.empty-state icon="package" title="Belum ada data produk" text="Tidak ada produk yang terjual pada periode ini." />
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
