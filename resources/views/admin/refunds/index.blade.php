@extends('layouts.admin')

@section('title', 'Refund')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Finance', ['label' => 'Refund']]" />
@endsection

@section('content')
    <x-admin.page-header title="Refund" subtitle="Nilai yang dikembalikan ke pelanggan." />

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total" :value="number_format($counts['all'], 0, ',', '.')" icon="undo" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Berhasil" :value="number_format($counts['succeeded'], 0, ',', '.')" icon="check" color="success" :hint="\App\Support\Currency::format($succeeded_amount)" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Menunggu" :value="number_format($counts['pending'], 0, ',', '.')" icon="clock" color="warning" :hint="\App\Support\Currency::format($pending_amount)" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Gagal" :value="number_format($counts['failed'] + $counts['rejected'], 0, ',', '.')" icon="x-circle" color="danger" />
        </div>
    </div>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.refunds.index')"
            :filters="[
                ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Nomor refund atau nomor pesanan'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => [
                    '' => 'Semua status',
                    'pending' => 'Menunggu',
                    'succeeded' => 'Berhasil',
                    'failed' => 'Gagal',
                    'rejected' => 'Ditolak',
                ]],
            ]"
        />
    </x-admin.card>

    <x-admin.card title="Daftar Refund" icon="undo" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Nomor Refund</th>
                        <th scope="col">Pesanan</th>
                        <th scope="col">Pelanggan</th>
                        <th scope="col" class="text-end">Nilai</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col">Alasan</th>
                        <th scope="col">Gateway</th>
                        <th scope="col">Waktu</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td><code>{{ $row['refund_number'] }}</code></td>
                            <td><a href="{{ route('admin.orders.show', $row['order_id']) }}">{{ $row['order_number'] }}</a></td>
                            <td>
                                {{ $row['customer'] }}
                                <small class="d-block text-secondary">{{ \Illuminate\Support\Str::headline($row['requested_by_type']) }}</small>
                            </td>
                            <td class="text-end fw-semibold">{{ $row['amount_formatted'] }}</td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="$row['status']"
                                    :color="match($row['status']) { 'succeeded' => 'success', 'pending' => 'warning', 'failed' => 'danger', 'rejected' => 'secondary', default => 'info' }"
                                    pill
                                />
                            </td>
                            <td>{{ \Illuminate\Support\Str::limit($row['reason'], 50) }}</td>
                            <td>
                                {{ $row['provider'] }}
                                <small class="d-block text-secondary">{{ $row['gateway_refund_id'] !== '' ? $row['gateway_refund_id'] : '-' }}</small>
                            </td>
                            <td class="text-nowrap">{{ $row['succeeded_at'] !== '' ? $row['succeeded_at'] : $row['created_at'] }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.refunds.show', $row['id']) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-admin.empty-state icon="undo" title="Belum ada refund" text="Tidak ada refund yang tercatat." />
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
