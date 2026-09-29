@extends('layouts.admin')

@section('title', 'Pemakaian Tenant')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['SaaS', ['label' => 'Pemakaian']]" />
@endsection

@section('content')
    <x-admin.page-header title="Pemakaian" subtitle="Meter penggunaan tiap tenant terhadap batas paketnya." />

    @unless ($enabled)
        <x-admin.alert type="info" :dismissible="false" title="SaaS belum aktif pada instalasi ini" icon="layers">
            Belum ada tenant. Meter di bawah sengaja kosong agar tidak terjadi kebocoran data antar tenant.
        </x-admin.alert>
    @endunless

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Tenant Terdaftar" :value="count($rows)" icon="globe" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total Produk" :value="number_format($totals['products'], 0, ',', '.')" icon="package" color="info" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total Vendor" :value="number_format($totals['vendors'], 0, ',', '.')" icon="store" color="success" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Halaman PSEO" :value="number_format($totals['pseo_pages'], 0, ',', '.')" icon="layers" color="warning" />
        </div>
    </div>

    <x-admin.card title="Meter per Tenant" icon="gauge" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Tenant</th>
                        <th scope="col" class="text-end">Produk</th>
                        <th scope="col" class="text-end">Vendor</th>
                        <th scope="col" class="text-end">Staf</th>
                        <th scope="col" class="text-end">Pesanan / Bulan</th>
                        <th scope="col" class="text-end">PSEO</th>
                        <th scope="col" class="text-end">AI</th>
                        <th scope="col" class="text-end">Penyimpanan</th>
                        <th scope="col" class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td><a href="{{ route('admin.tenants.show', $row['tenant_id']) }}">{{ $row['tenant'] }}</a></td>
                            @foreach (['products', 'vendors', 'staff', 'orders_per_month', 'pseo_pages', 'ai_requests'] as $key)
                                <td class="text-end">
                                    {{ $row['usage'][$key]['used_formatted'] }}
                                    @if ($row['usage'][$key]['limit'] !== null)
                                        <small class="d-block text-secondary">dari {{ number_format($row['usage'][$key]['limit'], 0, ',', '.') }}</small>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-end">{{ $row['usage']['storage_mb']['used_formatted'] }}</td>
                            <td class="text-center">
                                <x-admin.badge :text="$row['is_active'] ? 'Aktif' : 'Nonaktif'" :color="$row['is_active'] ? 'success' : 'secondary'" pill />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-admin.empty-state icon="gauge" title="Belum ada meter" text="Meter akan terisi setelah tenant terdaftar." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>
@endsection
