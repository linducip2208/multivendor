@extends('layouts.admin')

@section('title', 'Log Aktivitas Pelanggan')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Customers', ['label' => 'Aktivitas']]" />
@endsection

@section('content')
    <x-admin.page-header title="Log Aktivitas" subtitle="Jejak peristiwa yang tercatat pada akun pelanggan." />

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total Peristiwa" :value="$total_events" icon="activity" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Hari Ini" :value="$today_events" icon="clock" color="info" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Pelanggan Terlibat" :value="$unique_customers" icon="users" color="success" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Halaman Ini" :value="count($rows)" icon="list" color="secondary" />
        </div>
    </div>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.activity-log')"
            :filters="[
                ['name' => 'customer', 'label' => 'ID Pelanggan', 'type' => 'number', 'placeholder' => 'Contoh: 12'],
                ['name' => 'type', 'label' => 'Jenis', 'type' => 'select', 'options' => array_merge(['' => 'Semua jenis'], array_column($types, 'label', 'value'))],
            ]"
        />
    </x-admin.card>

    <x-admin.card title="Peristiwa" icon="activity" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Waktu</th>
                        <th scope="col">Pelanggan</th>
                        <th scope="col">Jenis</th>
                        <th scope="col">Keterangan</th>
                        <th scope="col">IP</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="text-nowrap">{{ $row['at'] }}</td>
                            <td>
                                <a href="{{ $row['url'] }}">{{ $row['customer'] }}</a>
                                <small class="d-block text-secondary">{{ $row['email'] }}</small>
                            </td>
                            <td><x-admin.badge :text="\Illuminate\Support\Str::headline($row['type'])" color="info" pill /></td>
                            <td>{{ $row['description'] !== '' ? $row['description'] : '-' }}</td>
                            <td><code class="small">{{ $row['ip_address'] !== '' ? $row['ip_address'] : '-' }}</code></td>
                            <td class="text-end">
                                <a href="{{ $row['url'] }}" class="btn btn-sm btn-outline-secondary">Buka 360</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-admin.empty-state icon="activity" title="Belum ada aktivitas" text="Tidak ada peristiwa yang cocok dengan filter ini." />
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
