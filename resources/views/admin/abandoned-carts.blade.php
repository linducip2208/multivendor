@extends('layouts.admin')

@section('title', 'Keranjang Tertinggal')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Pemasaran', ['label' => 'Keranjang Tertinggal']]" />
@endsection

@section('content')
    <x-admin.page-header title="Keranjang Tertinggal" subtitle="Keranjang yang tercatat tetapi belum berubah menjadi pesanan." />

    <div class="row g-3 mb-3">
        @foreach ($kpis as $kpi)
            <div class="col-6 col-xl">
                <x-admin.stat
                    :label="$kpi['label']"
                    :value="isset($kpi['suffix']) ? number_format((float) $kpi['value'], 1, ',', '.').$kpi['suffix'] : $kpi['value']"
                    :money="$kpi['money'] ?? false"
                    :icon="$kpi['icon']"
                    :color="$kpi['color']"
                    :hint="$kpi['hint']"
                />
            </div>
        @endforeach
    </div>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.abandoned-carts')"
            :filters="[
                ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Nama atau email'],
                ['name' => 'filter', 'label' => 'Status', 'type' => 'select', 'options' => [
                    '' => 'Semua',
                    'pending' => 'Belum dipulihkan',
                    'recovered' => 'Sudah dipulihkan',
                    'reminded' => 'Sudah diaperingatkan',
                ]],
            ]"
        />
    </x-admin.card>

    <x-admin.card title="Daftar Keranjang" icon="shopping-cart" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Pelanggan</th>
                        <th scope="col">Isi Keranjang</th>
                        <th scope="col" class="text-end">Nilai</th>
                        <th scope="col" class="text-center">Pengingat</th>
                        <th scope="col" class="text-center">Umur</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                {{ $row['customer'] }}
                                <small class="d-block text-secondary">{{ $row['email'] }}</small>
                            </td>
                            <td>
                                @forelse ($row['items'] as $item)
                                    <span class="d-block small">{{ $item['name'] }} &times; {{ $item['quantity'] }}</span>
                                @empty
                                    <span class="text-secondary small">Rincian tidak tersimpan</span>
                                @endforelse
                                <small class="d-block text-secondary">{{ $row['item_count'] }} item</small>
                            </td>
                            <td class="text-end fw-semibold">{{ $row['amount_formatted'] }}</td>
                            <td class="text-center">
                                <x-admin.badge :text="$row['reminder_count'].' / '.$row['reminder_limit']" :color="$row['reminder_count'] > 0 ? 'info' : 'secondary'" pill />
                            </td>
                            <td class="text-center">{{ $row['age_days'] }} hari</td>
                            <td class="text-center">
                                @if ($row['recovered_at'] !== '')
                                    <x-admin.badge text="Sudah Pulih" color="success" pill />
                                @else
                                    <x-admin.badge text="Menunggu" color="warning" pill />
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($row['can_remind'])
                                    <form method="POST" action="{{ route('admin.abandoned-carts.remind', $row['id']) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <x-admin.icon name="bell" :size="14" /> Ingatkan
                                        </button>
                                    </form>
                                @else
                                    <small class="text-secondary">
                                        {{ $row['recovered_at'] !== '' ? 'Selesai' : 'Batas pengingat tercapai' }}
                                    </small>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-admin.empty-state icon="shopping-cart" title="Belum ada keranjang tertinggal" text="Tidak ada keranjang yang cocok dengan filter ini." />
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
