@extends('layouts.admin')

@section('title', 'Tambah Staf')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['System', ['label' => 'Staf', 'href' => route('admin.users.index')], ['label' => 'Baru']]" />
@endsection

@section('content')
    <x-admin.page-header title="Tambah Staf" subtitle="Peran staf mendapat izin bawaan yang dapat disesuaikan." />

    <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-lg-5">
                <x-admin.card title="Akun" icon="user" class="mb-3">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-admin.form-field name="name" label="Nama Lengkap" required :maxlength="120" />
                        </div>
                        <div class="col-12">
                            <x-admin.form-field name="email" label="Email" type="email" required :maxlength="160" />
                        </div>
                        <div class="col-12">
                            <x-admin.form-field name="password" label="Kata Sandi" type="password" required :minlength="8" autocomplete="new-password" />
                        </div>
                        <div class="col-12">
                            <x-admin.form-field name="password_confirmation" label="Ulangi Kata Sandi" type="password" required :minlength="8" autocomplete="new-password" />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="employee-role">Peran</label>
                            <select class="form-select" id="employee-role" name="role" required>
                                @foreach ($roles as $role)
                                    <option value="{{ $role }}">{{ $role === 'admin' ? 'Administrator' : 'Staf' }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </x-admin.card>
            </div>

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
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Staf</button>
        </div>
    </form>
@endsection
