@extends('layouts.admin')

@section('title', 'Provider Kurir')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Fulfillment', ['label' => 'Kurir']]" />
@endsection

@section('content')
    <x-admin.page-header title="Provider Kurir" subtitle="Adapter kurir yang terhubung ke platform.">
        <x-slot:actions>
            <a href="{{ route('admin.providers.create') }}" class="btn btn-primary btn-sm">
                <x-admin.icon name="plus" :size="14" /> Tambah Provider
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.alert type="info" :dismissible="false" title="Kredensial tidak pernah ditampilkan" icon="lock">
        Kolom API key dan secret hanya disimpan dalam bentuk terenkripsi. Formulir di bawah menampilkan indikator apakah kredensial tersedia, bukan nilainya.
    </x-admin.alert>

    <x-admin.card title="Daftar Kurir" icon="globe" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Kurir</th>
                        <th scope="col">Format API</th>
                        <th scope="col">Host</th>
                        <th scope="col" class="text-center">Kredensial</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-end">Pengiriman</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                {{ $row['name'] }}
                                @if ($row['is_default'])
                                    <x-admin.badge text="Utama" color="primary" pill />
                                @endif
                            </td>
                            <td><code class="small">{{ $row['api_format'] }}</code></td>
                            <td>{{ $row['host'] }}</td>
                            <td class="text-center">
                                <x-admin.badge :text="$row['configured'] ? 'Terpasang' : 'Belum'" :color="$row['configured'] ? 'success' : 'warning'" pill />
                            </td>
                            <td class="text-center">
                                <x-admin.badge :text="$row['is_active'] ? 'Aktif' : 'Nonaktif'" :color="$row['is_active'] ? 'success' : 'secondary'" pill />
                            </td>
                            <td class="text-end">{{ number_format($row['recent_shipments'], 0, ',', '.') }}</td>
                            <td class="text-end">
                                @if ($row['id'] !== null)
                                    <a href="{{ route('admin.couriers.show', $row['id']) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
                                @else
                                    <span class="text-secondary small">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-admin.empty-state icon="globe" title="Belum ada provider kurir" text="Tambahkan provider di menu Integrasi untuk mulai melacak kiriman." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <div class="row g-3 mt-3">
        <div class="col-lg-6">
            <x-admin.card title="Kurir Aktif" icon="truck">
                @forelse ($configured_couriers as $courier)
                    <x-admin.badge :text="$courier" color="primary" pill class="me-1 mb-1" />
                @empty
                    <p class="text-secondary small mb-0">Tidak ada kurir aktif. Checkout akan memakai pengiriman manual.</p>
                @endforelse
            </x-admin.card>
        </div>
        <div class="col-lg-6">
            <x-admin.card title="Kurir Utama" icon="star">
                <p class="mb-0">
                    @if ($default_courier !== null)
                        <x-admin.badge :text="$default_courier" color="success" pill />
                    @else
                        <span class="text-secondary">Belum ditentukan</span>
                    @endif
                </p>
            </x-admin.card>
        </div>
    </div>
@endsection
