@extends('layouts.admin')

@section('title', 'Loyalty')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Customers', ['label' => 'Loyalty']]" />
@endsection

@section('content')
    <x-admin.page-header title="Loyalty" subtitle="Saldo poin pelanggan dan pergerakan terakhir." />

    <div class="row g-3 mb-3">
        @foreach ($kpis as $kpi)
            <div class="col-6 col-xl-3">
                <x-admin.stat
                    :label="$kpi['label']"
                    :value="$kpi['value']"
                    :icon="$kpi['icon']"
                    :color="$kpi['color']"
                    :hint="$kpi['hint']"
                />
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <x-admin.card title="Pelanggan" icon="users" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Pelanggan</th>
                                <th scope="col" class="text-end">Poin</th>
                                <th scope="col" class="text-center">Tier</th>
                                <th scope="col" class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td>
                                        <a href="{{ $row['url'] }}">{{ $row['name'] }}</a>
                                        <small class="d-block text-secondary">{{ $row['email'] }}</small>
                                    </td>
                                    <td class="text-end fw-semibold">{{ $row['points_formatted'] }}</td>
                                    <td class="text-center">
                                        <x-admin.badge
                                            :text="$row['tier']"
                                            :color="match($row['tier']) { 'Platinum' => 'dark', 'Emas' => 'warning', 'Perak' => 'info', default => 'secondary' }"
                                            pill
                                        />
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ $row['url'] }}" class="btn btn-sm btn-outline-secondary">Buka 360</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <x-admin.empty-state icon="award" title="Belum ada peserta" text="Poin pelanggan akan tampil setelah transaksi pertama menghasilkan poin." />
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
        </div>

        <div class="col-lg-4">
            <x-admin.card title="Sebaran Tier" icon="award" class="mb-3">
                <x-admin.chart
                    type="doughnut"
                    :labels="array_column($tiers, 'name')"
                    :data="[['label' => 'Pelanggan', 'data' => array_column($tiers, 'count')]]"
                    :height="240"
                />
            </x-admin.card>

            <x-admin.card title="Pergerakan Terakhir" icon="activity" flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Pelanggan</th>
                                <th scope="col" class="text-end">Poin</th>
                                <th scope="col">Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recent as $row)
                                <tr>
                                    <td>
                                        {{ $row['customer'] }}
                                        <small class="d-block text-secondary">{{ $row['description'] }}</small>
                                    </td>
                                    <td class="text-end">
                                        <x-admin.badge
                                            :text="($row['type'] === 'earn' ? '+' : '-').number_format(abs($row['points']), 0, ',', '.')"
                                            :color="$row['type'] === 'earn' ? 'success' : 'danger'"
                                            pill
                                        />
                                    </td>
                                    <td class="text-nowrap">{{ $row['at'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3"><x-admin.empty-state compact icon="activity" title="Belum ada pergerakan poin" text="Riwayat poin akan tampil di sini." /></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
    </div>
@endsection
