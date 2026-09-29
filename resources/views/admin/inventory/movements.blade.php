@extends('layouts.admin')

@section('title', 'Pergerakan Stok')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Commerce', ['label' => 'Inventori', 'href' => route('admin.inventory.index')], ['label' => 'Pergerakan']]" />
@endsection

@section('content')
    <x-admin.page-header title="Pergerakan Stok" subtitle="Jejak audit setiap perubahan stok, dari-adjustment, transfer, sampai opname.">
        <x-slot:actions>
            <a href="{{ route('admin.inventory.index') }}" class="btn btn-outline-secondary btn-sm">
                <x-admin.icon name="package" :size="14" /> Posisi Stok
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.inventory.movements')"
            :filters="[
                ['name' => 'search', 'label' => 'Cari produk', 'placeholder' => 'Nama, SKU, atau catatan'],
                ['name' => 'type', 'label' => 'Jenis', 'type' => 'select', 'options' => array_merge(['' => 'Semua jenis'], $report['types'])],
            ]"
        />
    </x-admin.card>

    <x-admin.card title="Riwayat" icon="activity" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Waktu</th>
                        <th scope="col">Produk</th>
                        <th scope="col">Gudang</th>
                        <th scope="col" class="text-center">Jenis</th>
                        <th scope="col" class="text-end">Jumlah</th>
                        <th scope="col" class="text-end">Saldo Setelah</th>
                        <th scope="col">Catatan</th>
                        <th scope="col">Pelaku</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['rows'] as $row)
                        <tr>
                            <td class="text-nowrap">{{ $row['at'] }}</td>
                            <td>
                                {{ $row['product'] }}
                                <small class="d-block text-secondary">{{ $row['sku'] }}</small>
                            </td>
                            <td>{{ $row['warehouse'] }}</td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="$row['type_label']"
                                    :color="match($row['type']) { 'in', 'return', 'release' => 'success', 'out', 'reservation' => 'warning', 'transfer' => 'info', default => 'secondary' }"
                                    pill
                                />
                            </td>
                            <td class="text-end fw-semibold {{ $row['quantity'] < 0 ? 'text-danger' : 'text-success' }}">
                                {{ $row['quantity'] > 0 ? '+' : '' }}{{ number_format($row['quantity'], 0, ',', '.') }}
                            </td>
                            <td class="text-end">{{ $row['balance_after'] === null ? '-' : number_format($row['balance_after'], 0, ',', '.') }}</td>
                            <td>{{ $row['note'] !== '' ? \Illuminate\Support\Str::limit($row['note'], 60) : '-' }}</td>
                            <td>{{ $row['creator'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-admin.empty-state
                                    icon="activity"
                                    title="Belum ada pergerakan"
                                    text="Setiap perubahan stok akan tercatat di sini secara otomatis."
                                />
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
