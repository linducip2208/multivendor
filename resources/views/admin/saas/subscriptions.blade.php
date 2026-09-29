@extends('layouts.admin')

@section('title', 'Langganan')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['SaaS', ['label' => 'Langganan']]" />
@endsection

@section('content')
    <x-admin.page-header title="Langganan" subtitle="Status langganan tenant beserta masa tenggangnya." />

    @unless ($feature_enabled && $counts['all'] > 0)
        <x-admin.alert type="info" :dismissible="false" title="SaaS belum aktif pada instalasi ini" icon="layers">
            Belum ada langganan yang tercatat. Daftar di bawah kosong karena tidak ada data, bukan karena disembunyikan.
        </x-admin.alert>
    @endunless

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total" :value="number_format($counts['all'], 0, ',', '.')" icon="refresh" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Aktif" :value="number_format($counts['active'], 0, ',', '.')" icon="check" color="success" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Masa Uji" :value="number_format($counts['trialing'], 0, ',', '.')" icon="clock" color="info" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="MRR" :value="$mrr" money icon="cash" color="success" :hint="'Pendapatan berulang per bulan'" />
        </div>
    </div>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.saas.subscriptions')"
            :filters="[
                ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Nama atau slug tenant'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => array_merge(['' => 'Semua status'], \App\Services\Backoffice\SaasService::SUBSCRIPTION_STATUSES)],
            ]"
        />
    </x-admin.card>

    <x-admin.alert type="warning" :dismissible="false" title="Masa tenggang" icon="alert-triangle">
        Langganan yang berakhir masih mendapat toleransi {{ $grace_days }} hari sebelum ditandai kedaluwarsa. Selama masa itu tenant tetap dapat dipakai.
    </x-admin.alert>

    <x-admin.card title="Daftar Langganan" icon="refresh" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Tenant</th>
                        <th scope="col">Paket</th>
                        <th scope="col" class="text-center">Siklus</th>
                        <th scope="col" class="text-end">Nilai</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col">Mulai</th>
                        <th scope="col">Berakhir</th>
                        <th scope="col">Tenggang</th>
                        <th scope="col" class="text-end">Ubah Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <a href="{{ route('admin.tenants.show', $row['tenant_id']) }}">{{ $row['tenant'] }}</a>
                            </td>
                            <td>{{ $row['plan'] }}</td>
                            <td class="text-center">{{ \Illuminate\Support\Str::headline($row['billing_cycle']) }}</td>
                            <td class="text-end fw-semibold">{{ $row['price_formatted'] }}</td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="$row['status_label']"
                                    :color="match($row['status']) { 'active' => 'success', 'trialing' => 'info', 'past_due' => 'warning', 'cancelled' => 'secondary', default => 'danger' }"
                                    pill
                                />
                            </td>
                            <td class="text-nowrap">{{ $row['starts_at'] !== '' ? $row['starts_at'] : '-' }}</td>
                            <td class="text-nowrap">{{ $row['ends_at'] !== '' ? $row['ends_at'] : '-' }}</td>
                            <td class="text-nowrap">
                                {{ $row['grace_ends_at'] !== '' ? $row['grace_ends_at'] : '-' }}
                                @if ($row['on_grace'])
                                    <x-admin.badge text="Masa tenggang" color="warning" pill />
                                @endif
                            </td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.saas.subscriptions.status', $row['id']) }}" class="d-flex gap-1">
                                    @csrf
                                    @method('PUT')
                                    <label class="visually-hidden" for="status-{{ $row['id'] }}">Status langganan {{ $row['tenant'] }}</label>
                                    <select class="form-select form-select-sm" id="status-{{ $row['id'] }}" name="status">
                                        @foreach (\App\Services\Backoffice\SaasService::SUBSCRIPTION_STATUSES as $value => $label)
                                            <option value="{{ $value }}" @selected($row['status'] === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-admin.empty-state icon="refresh" title="Belum ada langganan" text="Langganan akan muncul setelah tenant memilih paket." />
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
