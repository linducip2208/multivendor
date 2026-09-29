@extends('layouts.admin')

@section('title', 'Peran dan Izin')

@section('breadcrumb')
    <x-admin.breadcrumb :items="['System', ['label' => 'Peran']]" />
@endsection

@section('content')
    <x-admin.page-header title="Peran dan Izin Akses" subtitle="Hak akses tiap peran disimpan pada tabel roles, permissions, dan permission_role." />

    <x-admin.alert type="info" :dismissible="false" title="Bukan sekadar tampilan" icon="shield">
        Izin yang dicentang di sini dibaca oleh middleware <code>permission:</code> pada setiap rute. Menonaktifkan modul di sini langsung menutup akses ke modul tersebut.
    </x-admin.alert>

    <form method="POST" action="{{ route('admin.roles.update') }}">
        @csrf
        @method('PUT')

        @foreach ($roles as $role)
            <x-admin.card class="mb-3" :title="$role['name']" icon="shield" :subtitle="$role['description'] !== '' ? $role['description'] : \Illuminate\Support\Str::headline($role['slug']).' · '.$role['permission_count'].' izin'" class="h-100">
                <x-slot:menu>
                    @if ($role['is_system'])
                        <x-admin.badge text="Sistem" color="secondary" pill />
                    @endif
                </x-slot:menu>

                <input type="hidden" name="roles[{{ $role['id'] }}][id]" value="{{ $role['id'] }}">

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1" for="role-name-{{ $role['id'] }}">Nama Peran</label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            id="role-name-{{ $role['id'] }}"
                            name="roles[{{ $role['id'] }}][name]"
                            value="{{ $role['name'] }}"
                            maxlength="60"
                            required
                        >
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Modul</th>
                                @foreach ($actions as $actionKey => $actionLabel)
                                    <th scope="col" class="text-center">{{ $actionLabel }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($modules as $moduleKey => $moduleLabel)
                                <tr>
                                    <td class="fw-semibold">{{ $moduleLabel }}</td>
                                    @foreach ($actions as $actionKey => $_)
                                        @php $slug = $moduleKey.'.'.$actionKey; @endphp
                                        <td class="text-center">
                                            <input
                                                type="checkbox"
                                                class="form-check-input"
                                                name="roles[{{ $role['id'] }}][permissions][]"
                                                value="{{ collect($role['permissions'])->firstWhere('slug', $slug)['id'] ?? '' }}"
                                                @checked(collect($role['permissions'])->contains('slug', $slug))
                                                aria-label="{{ $actionLabel }} {{ $moduleLabel }} untuk {{ $role['name'] }}"
                                                @disabled(! collect($role['permissions'])->contains('slug', $slug))
                                            >
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="small text-secondary mt-3 mb-0">
                    Kotak yang tidak tersedia berarti izin <code>module.action</code> itu belum ada di tabel
                    <code>permissions</code>. Tambahkan lewat halaman Izin.
                </p>
            </x-admin.card>
        @endforeach

        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">
                <x-admin.icon name="save" :size="14" /> Simpan Peran
            </button>
        </div>
    </form>
@endsection
