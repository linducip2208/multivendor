@extends('layouts.admin')

@section('title', 'Providers')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Sistem', ['label' => 'Provider']]" />
@endsection

@section('content')
    <x-admin.page-header title="Provider Integrasi" subtitle="Adapter untuk pembayaran, kurir, SMS, email, AI, dan penyimpanan.">
        <x-slot:actions>
            <a href="{{ route('admin.providers.create') }}" class="btn btn-primary btn-sm">
                <x-admin.icon name="plus" :size="14" /> Tambah Provider
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.alert type="info" :dismissible="false" title="Kredensial bersifat tulis-saja" icon="lock">
        API key dan secret disimpan terenkripsi. Daftar dan formulir hanya menampilkan topi yang sudah disamarkan, bukan nilai aslinya. Mengosongkan kolom kredensial berarti kredensial lama dipertahankan.
    </x-admin.alert>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.providers.index')"
            :filters="[['name' => 'type', 'label' => 'Tipe', 'type' => 'select', 'options' => array_merge(['Semua tipe' => ''], array_combine($types, array_map('ucfirst', $types)))]]
        />
    </x-admin.card>

    <x-admin.card title="Daftar Provider" icon="plug" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Nama</th>
                        <th scope="col">Tipe</th>
                        <th scope="col">Format</th>
                        <th scope="col">Host</th>
                        <th scope="col">Kredensial</th>
                        <th scope="col" class="text-center">Status</th>
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
                                @if ($row['description'] !== '')
                                    <small class="d-block text-secondary">{{ \Illuminate\Support\Str::limit($row['description'], 60) }}</small>
                                @endif
                            </td>
                            <td>{{ \Illuminate\Support\Str::headline($row['type']) }}</td>
                            <td><code class="small">{{ $row['api_format'] }}</code></td>
                            <td>{{ $row['host'] }}</td>
                            <td>
                                @if ($row['has_key'])
                                    <x-admin.badge :text="$row['api_key_mask']" color="secondary" pill />
                                @else
                                    <span class="text-secondary small">Tidak ada kunci</span>
                                @endif
                                @if ($row['has_secret'])
                                    <x-admin.badge :text="$row['api_secret_mask']" color="secondary" pill />
                                @endif
                            </td>
                            <td class="text-center">
                                <x-admin.badge :text="$row['is_active'] ? 'Aktif' : 'Nonaktif'" :color="$row['is_active'] ? 'success' : 'secondary'" pill />
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Aksi provider {{ $row['name'] }}">
                                    <a href="{{ route('admin.providers.edit', $row['id']) }}" class="btn btn-outline-primary">Ubah</a>
                                    <x-admin.confirmation-form
                                        :action="route('admin.providers.destroy', $row['id'])"
                                        message="Provider ini akan dihapus. Transaksi yang memakai provider ini tidak dapat diproses. Lanjutkan?"
                                        label="Hapus"
                                        variant="outline-danger"
                                        icon="trash"
                                        size="btn-sm"
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-admin.empty-state icon="plug" title="Belum ada provider" text="Tambahkan provider pertama untuk menghubungkan layanan eksternal." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <div class="mt-3">
        <x-admin.pagination :paginator="$providers" size="sm" />
    </div>
@endsection
