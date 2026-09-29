@extends('layouts.admin')

@section('title', 'Izin Akses')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['System', ['label' => 'Peran', 'href' => route('admin.roles.index')], ['label' => 'Izin']]" />
@endsection

@section('content')
    <x-admin.page-header title="Katalog Izin" subtitle="Daftar izin yang dibaca middleware pada setiap rute." />

    <x-admin.alert type="info" :dismissible="false" title="Cara kerja" icon="key-square">
        Format izin mengikuti <code>module.action</code>. Middleware menerima lebih dari satu slug dan mengizinkan akses bila salah satu cocok, sehingga <code>permission:orders.view,orders.edit</code> berarti salah satu cukup.
    </x-admin.alert>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Total Izin" :value="number_format($total, 0, ',', '.')" icon="key" color="primary" />
        </div>
        <div class="col-6 col-xl-3">
            <x-admin.stat label="Modul" :value="count($modules)" icon="layers" color="info" />
        </div>
    </div>

    <x-admin.card title="Ubah Label Izin" icon="edit-3" class="mb-3">
        <form method="POST" action="{{ route('admin.permissions.update') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-12 col-md-5">
                <label class="form-label small mb-1" for="permission-id">Izin</label>
                <select class="form-select form-select-sm" id="permission-id" name="ids[]" required>
                    @foreach ($grouped as $module => $permissions)
                        <optgroup label="{{ $module }}">
                            @foreach ($permissions as $permission)
                                <option value="{{ $permission['id'] }}">{{ $permission['slug'] }} — {{ $permission['name'] }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small mb-1" for="permission-name">Label Baru</label>
                <input type="text" class="form-control form-control-sm" id="permission-name" name="name" maxlength="100" placeholder="Contoh: Lihat Laporan">
            </div>
            <div class="col-12 col-md-3">
                <button type="submit" class="btn btn-outline-primary">Terapkan</button>
            </div>
        </form>
    </x-admin.card>

    @foreach ($grouped as $module => $permissions)
        <x-admin.card class="mb-3" :title="$module" icon="layers" :subtitle="count($permissions).' izin'" flush>
            <div class="table-responsive">
                <table class="table admin-table mb-0 table-hover">
                    <thead>
                        <tr>
                            <th scope="col">Slug</th>
                            <th scope="col">Label</th>
                            <th scope="col">Kelompok</th>
                            <th scope="col">Peran</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($permissions as $permission)
                            <tr>
                                <td><code class="small">{{ $permission['slug'] }}</code></td>
                                <td>{{ $permission['name'] }}</td>
                                <td>{{ $permission['group'] !== '' ? \Illuminate\Support\Str::headline($permission['group']) : '-' }}</td>
                                <td>
                                    @forelse ($permission['roles'] as $roleName)
                                        <x-admin.badge :text="$roleName" color="primary" pill />
                                    @empty
                                        <span class="text-secondary small">Belum diberikan</span>
                                    @endforelse
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-admin.card>
    @endforeach
@endsection
