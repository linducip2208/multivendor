@extends('layouts.admin')

@section('title', 'Settlement')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Finance', ['label' => 'Settlement']]" />
@endsection

@section('content')
    <x-admin.page-header title="Settlement" subtitle="Permintaan penarikan saldo oleh vendor." />

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total Permintaan" :value="number_format($counts['all'], 0, ',', '.')" icon="download" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Menunggu" :value="number_format($counts['pending'], 0, ',', '.')" icon="clock" color="warning" :hint="\App\Support\Currency::format($pending_amount).' belum diproses'" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Disetujui" :value="number_format($counts['approved'], 0, ',', '.')" icon="check" color="info" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Sudah Dibayar" :value="number_format($counts['completed'], 0, ',', '.')" icon="cash" color="success" :hint="\App\Support\Currency::format($paid_amount).' dibayarkan'" />
        </div>
    </div>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.settlements.index')"
            :filters="[
                ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Nama vendor atau toko'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => [
                    '' => 'Semua status',
                    'pending' => 'Menunggu',
                    'approved' => 'Disetujui',
                    'completed' => 'Selesai',
                    'rejected' => 'Ditolak',
                ]],
            ]"
        />
    </x-admin.card>

    <x-admin.card title="Daftar Settlement" icon="download" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Vendor</th>
                        <th scope="col">Toko</th>
                        <th scope="col">Rekening</th>
                        <th scope="col" class="text-end">Nominal</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col">Disetujui Oleh</th>
                        <th scope="col">Diajukan</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                {{ $row['vendor'] }}
                                <small class="d-block text-secondary">{{ $row['bank_name'] }}</small>
                            </td>
                            <td>{{ $row['shop'] }}</td>
                            <td><code class="small">{{ $row['bank_account'] }}</code></td>
                            <td class="text-end fw-semibold">{{ $row['amount_formatted'] }}</td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="$row['status']"
                                    :color="match($row['status']) { 'pending' => 'warning', 'approved' => 'info', 'completed' => 'success', default => 'danger' }"
                                    pill
                                />
                            </td>
                            <td>
                                {{ $row['approver'] !== '' ? $row['approver'] : '-' }}
                                @if ($row['approved_at'] !== '')
                                    <small class="d-block text-secondary">{{ $row['approved_at'] }}</small>
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $row['created_at'] }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.settlements.show', $row['id']) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-admin.empty-state icon="download" title="Belum ada permintaan" text="Permintaan penarikan saldo akan muncul di sini." />
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
