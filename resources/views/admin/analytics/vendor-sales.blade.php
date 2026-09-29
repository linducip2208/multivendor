@extends('layouts.admin')

@section('title', 'Laporan Penjualan Vendor')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Reports', ['label' => 'Penjualan Vendor']]" />
@endsection

@section('content')
    <x-admin.page-header title="Penjualan per Vendor" subtitle="Omzet, komisi platform, dan bagian yang dibayarkan ke mitra." />

    <div class="card mb-3">
        <div class="card-body">
            @include('admin.partials.date-range', ['range' => $range])
        </div>
    </div>

    <x-admin.tabs :tabs="$tabs" class="mb-3" />

    <div class="row g-3 mb-3">
        @foreach ($report['kpis'] as $kpi)
            <div class="col-6 col-xl-3">
                <x-admin.stat :label="$kpi['label']" :value="$kpi['value']" :money="$kpi['money'] ?? false" />
            </div>
        @endforeach
    </div>

    <x-admin.card title="Rincian per Toko" icon="store" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Toko</th>
                        <th scope="col">Kota</th>
                        <th scope="col" class="text-end">Produk</th>
                        <th scope="col" class="text-end">Terjual</th>
                        <th scope="col" class="text-end">Transaksi</th>
                        <th scope="col" class="text-end">Omzet</th>
                        <th scope="col" class="text-end">Komisi</th>
                        <th scope="col" class="text-end">Diterima Mitra</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['rows'] as $row)
                        <tr>
                            <td><a href="{{ route('admin.vendors.show', $row['id']) }}">{{ $row['name'] }}</a></td>
                            <td>{{ $row['city'] }}</td>
                            <td class="text-end">{{ number_format($row['products'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($row['sold'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($row['transactions'], 0, ',', '.') }}</td>
                            <td class="text-end fw-semibold">{{ \App\Support\Currency::format($row['revenue']) }}</td>
                            <td class="text-end text-danger">{{ \App\Support\Currency::format($row['commission']) }}</td>
                            <td class="text-end text-success">{{ \App\Support\Currency::format($row['payout']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-admin.empty-state icon="store" title="Belum ada toko aktif" text="Tidak ada toko yang dapat ditampilkan." />
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
