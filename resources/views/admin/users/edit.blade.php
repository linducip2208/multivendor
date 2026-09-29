@extends('layouts.admin')

@section('title', 'Ubah '.$employee['name'])

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Sistem', ['label' => 'Staf', 'href' => route('admin.users.index')], ['label' => $employee['name']]]" />
@endsection

@section('content')
    <x-admin.page-header :title="'Ubah '.$employee['name']" :subtitle="$employee['email'].' · '.$employee['role_label']">
        <x-slot:actions>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if ($employee['is_super_admin'])
        <x-admin.alert type="warning" :title="'Akun super admin'" icon="shield">
            Nama dan email masih dapat diubah, tetapi peran dan izin akses akun ini tidak dapat diubah dari halaman ini.
        </x-admin.alert>
    @endif

    <form method="POST" action="{{ route('admin.users.update', $employee['id']) }}">
        @csrf
        @method('PUT')
        <div class="row g-3">
            <div class="col-lg-5">
                <x-admin.card title="Akun" icon="user" class="mb-3">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-admin.form-field name="name" label="Nama Lengkap" :value="$employee['name']" required :maxlength="120" />
                        </div>
                        <div class="col-12">
                            <x-admin.form-field name="email" label="Email" type="email" :value="$employee['email']" required :maxlength="160" />
                        </div>
                        <div class="col-12">
                            <x-admin.form-field
                                name="password"
                                label="Kata Sandi Baru"
                                type="password"
                                :minlength="8"
                                autocomplete="new-password"
                                help="Kosongkan bila tidak ingin mengubah kata sandi."
                            />
                        </div>
                        <div class="col-12">
                            <x-admin.form-field name="password_confirmation" label="Ulangi Kata Sandi" type="password" :minlength="8" autocomplete="new-password" />
                        </div>
                        @unless ($employee['is_super_admin'])
                            <div class="col-12">
                                <label class="form-label" for="employee-role">Peran</label>
                                <select class="form-select" id="employee-role" name="role" required>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role }}" @selected($employee['role'] === $role)>
                                            {{ $role === 'admin' ? 'Administrator' : 'Staf' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endunless
                    </div>
                </x-admin.card>
            </div>

            @unless ($employee['is_super_admin'])
                <div class="col-lg-7">
                    <x-admin.card title="Izin Akses" icon="key-square" subtitle="Kosongkan untuk memakai izin bawaan peran.">
                        @foreach ($permissions as $group)
                            <p class="fw-semibold small text-secondary mb-1">{{ $group['group'] }}</p>
                            <div class="row g-2 mb-3">
                                @foreach ($group['options'] as $permission)
                                    <div class="col-12 col-md-6">
                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="permissions[]"
                                                value="{{ $permission['id'] }}"
                                                id="perm-{{ $permission['id'] }}"
                                                @checked(in_array($permission['id'], $selectedPermissions, true))
                                                aria-label="{{ $permission['name'] }}"
                                            >
                                            <label class="form-check-label small" for="perm-{{ $permission['id'] }}">
                                                {{ $permission['name'] }}
                                                <code class="text-secondary">{{ $permission['slug'] }}</code>
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </x-admin.card>
                </div>
            @endunless
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
    </form>
@endsection
