@extends('layouts.admin')

@section('title', 'Affiliate')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Marketing', ['label' => 'Affiliate']]" />
@endsection

@section('content')
    <x-admin.page-header title="Affiliate" subtitle="Mitra promosi, klik yang tercatat, dan komisi yang dibayarkan." />

    <div class="row g-3 mb-3">
        @foreach ($kpis as $kpi)
            <div class="col-6 col-xl">
                <x-admin.stat :label="$kpi['label']" :value="$kpi['value']" :money="$kpi['money'] ?? false" :icon="$kpi['icon']" :color="$kpi['color']" :hint="$kpi['hint']" />
            </div>
        @endforeach
    </div>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.affiliates.index')"
            :filters="[
                ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Nama, kode, atau email'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => array_merge(['' => 'Semua status'], array_combine($statuses, array_map('ucfirst', $statuses)))],
            ]"
        />
    </x-admin.card>

    <x-admin.card title="Daftar Affiliate" icon="link" flush>
        <x-slot:menu>
            <form method="POST" action="{{ route('admin.affiliates.update') }}" class="d-flex align-items-center gap-2">
                @csrf
                <label class="visually-hidden" for="affiliate-status">Status baru</label>
                <select class="form-select form-select-sm" id="affiliate-status" name="status" required>
                    @foreach ($statuses as $value)
                        <option value="{{ $value }}">{{ ucfirst($value) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-primary" disabled data-bulk-submit>Perbarui Terpilih</button>
            </form>
        </x-slot:menu>

        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover" data-bulk-table>
                <thead>
                    <tr>
                        <th scope="col" style="width: 36px">
                            <input type="checkbox" class="form-check-input" data-bulk-select-all aria-label="Pilih semua baris">
                        </th>
                        <th scope="col">Affiliate</th>
                        <th scope="col">Kode</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-end">Rate</th>
                        <th scope="col" class="text-end">Klik</th>
                        <th scope="col" class="text-end">Pesanan</th>
                        <th scope="col" class="text-end">Omzet</th>
                        <th scope="col" class="text-end">Komisi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    name="ids[]"
                                    value="{{ $row['id'] }}"
                                    data-bulk-select
                                    aria-label="Pilih {{ $row['name'] }}"
                                >
                            </td>
                            <td>
                                {{ $row['name'] }}
                                <small class="d-block text-secondary">{{ $row['email'] }}</small>
                            </td>
                            <td><code>{{ $row['code'] }}</code></td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="ucfirst($row['status'])"
                                    :color="match($row['status']) { 'active' => 'success', 'pending' => 'warning', 'rejected' => 'danger', default => 'secondary' }"
                                    pill
                                />
                            </td>
                            <td class="text-end">{{ number_format($row['commission_rate'], 2, ',', '.') }}%</td>
                            <td class="text-end">{{ number_format($row['clicks'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($row['total_orders'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ $row['total_revenue_formatted'] }}</td>
                            <td class="text-end fw-semibold">{{ $row['total_commission_formatted'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-admin.empty-state icon="link" title="Belum ada affiliate" text="Affiliate terdaftar akan tampil di sini beserta klik dan komisinya." />
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
