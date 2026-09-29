@extends('layouts.admin')

@section('title', 'Staf Backoffice')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['System', ['label' => 'Pengguna', 'href' => route('admin.users.index')], ['label' => 'Staf']]" />
@endsection

@section('content')
    <x-admin.page-header title="Staf Backoffice" subtitle="Akun yang dapat masuk ke panel administrasi.">
        <x-slot:actions>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">
                <x-admin.icon name="plus" :size="14" /> Tambah Staf
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.alert type="info" :dismissible="false" title="Peran staf" icon="users">
        Peran <code>employee</code> dapat masuk ke panel dan diberi izin secara terpisah. Peran <code>admin</code> memiliki akses penuh. Akun super admin tidak dapat diubah oleh staf biasa.
    </x-admin.alert>

    <x-admin.card class="mb-3" title="Filter" icon="filter">
        <x-admin.filters
            :action="route('admin.users.index')"
            :filters="[['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Nama atau email']]
        />
    </x-admin.card>

    <x-admin.card title="Daftar Staf" icon="users" flush>
        <div class="table-responsive">
            <table class="table admin-table mb-0 table-hover">
                <thead>
                    <tr>
                        <th scope="col">Nama</th>
                        <th scope="col">Email</th>
                        <th scope="col" class="text-center">Peran</th>
                        <th scope="col" class="text-end">Izin</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col">Bergabung</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <x-admin.avatar :name="$row['name']" size="sm" />
                                {{ $row['name'] }}
                                @if ($row['is_super_admin'])
                                    <x-admin.badge text="Super Admin" color="dark" pill />
                                @endif
                            </td>
                            <td class="text-break">{{ $row['email'] }}</td>
                            <td class="text-center">
                                <x-admin.badge :text="$row['role_label']" :color="$row['role'] === 'admin' ? 'primary' : 'secondary'" pill />
                            </td>
                            <td class="text-end">{{ number_format($row['permission_count'], 0, ',', '.') }}</td>
                            <td class="text-center">
                                <x-admin.badge
                                    :text="$row['status'] === 'active' ? 'Aktif' : 'Nonaktif'"
                                    :color="$row['status'] === 'active' ? 'success' : 'secondary'"
                                    pill
                                />
                            </td>
                            <td class="text-nowrap">{{ $row['joined'] }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Aksi {{ $row['name'] }}">
                                    <a href="{{ route('admin.users.edit', $row['id']) }}" class="btn btn-outline-primary">Ubah</a>
                                    <x-admin.confirmation-form
                                        :action="route('admin.users.destroy', $row['id'])"
                                        message="Akun staf ini akan dihapus. Lanjutkan?"
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
                                <x-admin.empty-state icon="users" title="Belum ada staf" text="Tambahkan akun pertama yang boleh masuk ke panel." />
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
