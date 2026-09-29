@extends('layouts.admin')

@section('title', 'Analitik Eksekutif')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Analytics', ['label' => 'Eksekutif']]" />
@endsection

@section('content')
    <x-admin.page-header title="Ringkasan Eksekutif" subtitle="Kinerja platform untuk periode terpilih.">
        <x-slot:actions>
            <x-admin.dropdown label="Unduh" icon="download" variant="outline-secondary" size="sm">
                <x-admin.dropdown-item :href="route('admin.export.orders', request()->query())" icon="file-bar">Pesanan (CSV)</x-admin.dropdown-item>
                <x-admin.dropdown-item :href="route('admin.export.products', request()->query())" icon="package">Produk (CSV)</x-admin.dropdown-item>
                <x-admin.dropdown-item :href="route('admin.export.customers', request()->query())" icon="users">Pelanggan (CSV)</x-admin.dropdown-item>
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
        @foreach (['gmv', 'net_revenue', 'orders', 'aov'] as $key)
            @php $tile = $summary[$key] ?? null; @endphp
            @if ($tile)
                <div class="col-6 col-xl-3">
                    <x-admin.stat
                        :label="$tile['label']"
                        :value="$tile['value']"
                        :money="$tile['money']"
                        :hint="$tile['hint'] ?? null"
                        :trend="$tile['trend']['direction'] ?? null"
                        :trend-label="isset($tile['trend']) ? ($tile['trend']['direction'] === 'up' ? '+' : ($tile['trend']['direction'] === 'down' ? '' : '')).number_format((float) $tile['trend']['value'], 1, ',', '.').'%' : null"
                        :icon="match($key) { 'gmv' => 'cash', 'net_revenue' => 'trending-up', 'orders' => 'shopping-cart', default => 'calculator' }"
                        :color="match($key) { 'gmv' => 'success', 'net_revenue' => 'primary', 'orders' => 'info', default => 'warning' }"
                    />
                </div>
            @endif
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        @foreach (['customers', 'vendors', 'products', 'conversion', 'cancelled', 'refunds', 'commission', 'payouts'] as $key)
            @php $tile = $summary[$key] ?? null; @endphp
            @if ($tile)
                <div class="col-6 col-md-4 col-xl-3">
                    <x-admin.stat
                        :label="$tile['label']"
                        :value="$tile['value']"
                        :money="$tile['money']"
                        :hint="$tile['hint'] ?? null"
                        :icon="match($key) { 'customers' => 'users', 'vendors' => 'store', 'products' => 'package', 'conversion' => 'target', 'cancelled' => 'x-circle', 'refunds' => 'undo', 'commission' => 'percent', default => 'wallet' }"
                        :color="match($key) { 'cancelled' => 'danger', 'refunds' => 'warning', 'commission' => 'success', default => 'info' }"
                    />
                </div>
            @endif
        @endforeach
    </div>

    <x-admin.card title="Tren GMV" subtitle="Nilai dan jumlah pesanan per hari." icon="trending-up">
        <x-admin.chart
            id="executive-gmv"
            type="line"
            :labels="$summary['series']['labels'] ?? []"
            :data="[
                ['label' => 'GMV', 'data' => $summary['series']['gmv'] ?? []],
                ['label' => 'Pesanan', 'data' => $summary['series']['orders'] ?? []],
            ]"
            :height="300"
        />
    </x-admin.card>
@endsection
