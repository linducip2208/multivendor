@extends('layouts.vendor')
@include('vendor.partials.helpers')

@section('title', 'Anggota tim')
@section('subtitle', $used.' dari '.($limit > 0 ? $limit : '∞').' kuota paket terpakai')

@section('breadcrumb', [
    ['label' => 'Vendor', 'href' => route('vendor.dashboard')],
    ['label' => 'Anggota tim'],
])

@section('content')
    @if ($limit > 0 && $used >= $limit)
        <x-admin.alert type="warning" title="Kuota tim penuh">
            Paket Anda sudah mencapai batas {{ $limit }} anggota aktif.
            <a href="{{ route('vendor.subscription.index') }}">Tingkatkan paket</a> untuk menambah anggota.
        </x-admin.alert>
    @endif

    <x-admin.filters
        :action="route('vendor.staff.index')"
        :filters="[['name' => 'search', 'label' => 'Cari anggota', 'placeholder' => 'Nama atau email']]"
    />

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-admin.card :padding="false">
                <x-admin.table>
                    <x-slot:table>
                        \App\Support\TableBuilder::make()
                            ->columns([
                                'member' => ['label' => 'Anggota', 'width' => '34%'],
                                'role' => ['label' => 'Peran'],
                                'permissions' => ['label' => 'Izin'],
                                'joined' => ['label' => 'Bergabung', 'align' => 'end'],
                                'status' => ['label' => 'Status'],
                                'actions' => ['label' => '', 'align' => 'end', 'width' => '90px'],
                            ])
                            ->rows(
                                $staff->map(fn ($member) => [
                                    'member' => '<span class="fw-medium d-block text-truncate">'.e($member->name).'</span><span class="text-secondary small text-truncate d-block">'.e($member->email).'</span>',
                                    'role' => '<span class="badge bg-primary-lt text-primary">'.e($roles[$member->role] ?? $member->role).'</span>',
                                    'permissions' => '<span class="text-secondary small">'.e($member->permissions ? count((array) json_decode((string) $member->permissions, true)) . ' izin' : 'Semua akses paket').'</span>',
                                    'joined' => '<span class="text-secondary small">'.e($member->joined_at ? \Carbon\Carbon::parse($member->joined_at)->format('d/m/Y') : ($member->invited_at ? 'Menunggu' : '—')).'</span>',
                                    'status' => $__status($member->is_active ? 'active' : 'inactive'),
                                    'actions' => $member->is_active
                                        ? '<form method="POST" action="'.route('vendor.staff.destroy', $member->id).'" data-confirm="Cabut akses '.e($member->name).'?">'
                                            .csrf().'@method("DELETE")'
                                            .'<button type="submit" class="btn btn-sm btn-ghost-danger" aria-label="Cabut akses"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.8"/><path d="M4.5 21v-1.2a6 6 0 0 1 6-6h3a6 6 0 0 1 6 6V21"/></svg></button></form>'
                                        : '<span class="text-secondary small">Nonaktif</span>',
                                ])->all()
                            )
                            ->empty('Belum ada anggota tim. Undang kasir atau staf Anda agar aktivitas dapat diaudit.')
                    </x-slot:table>
                </x-admin.table>
            </x-admin.card>
        </div>

        <div class="col-12 col-xl-4">
            <x-admin.card title="Undang anggota" icon="user-plus">
                <form method="POST" action="{{ route('vendor.staff.store') }}">
                    @csrf

                    <x-admin.form-field name="name" label="Nama lengkap" required />
                    <x-admin.form-field name="email" label="Email" type="email" required />
                    <x-admin.form-field name="phone" label="Telepon" type="tel" />
                    <x-admin.form-field name="role" label="Peran" type="select" required :options="$roles" />

                    <label class="form-label">Izin</label>
                    <div class="border rounded p-2 mb-3" style="max-height: 190px; overflow-y: auto;">
                        @foreach ($permissions as $permission)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission }}" id="perm-{{ $permission }}">
                                <label class="form-check-label small" for="perm-{{ $permission }}">{{ $permission }}</label>
                            </div>
                        @endforeach
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <x-admin.icon name="user-plus" :size="16" class="me-1" />
                        <span>Kirim undangan</span>
                    </button>
                </form>
            </x-admin.card>

            <x-admin.card title="Ringkasan" icon="info" class="mt-3">
                <dl class="row mb-0 small">
                    <dt class="col-6 text-secondary fw-normal">Aktif</dt>
                    <dd class="col-6 text-end">{{ $active }}</dd>
                    <dt class="col-6 text-secondary fw-normal">Belum bergabung</dt>
                    <dd class="col-6 text-end">{{ $pending }}</dd>
                    <dt class="col-6 text-secondary fw-normal">Kuota paket</dt>
                    <dd class="col-6 text-end">{{ $limit > 0 ? $limit : 'Tak terbatas' }}</dd>
                </dl>
            </x-admin.card>

            @isset($matrix)
                <x-admin.card title="Izin per menu" icon="shield" class="mt-3">
                    <ul class="list-unstyled mb-0 small">
                        @foreach ($matrix['menus'] as $key => $menu)
                            <li class="py-1 {{ $loop->last ? '' : 'border-bottom' }}">
                                <span class="fw-medium d-block">{{ $menu['label'] }}</span>
                                <span class="text-secondary">{{ implode(', ', $menu['permissions']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="text-secondary small mb-0 mt-2">Peran bawaan: manajer memegang semua izin, kasir/staf hanya lihat produk dan pesanan, keuangan hanya lihat keuangan, gudang kelola inventori, CS kelola pelanggan.</p>
                </x-admin.card>
            @endisset
        </div>
    </div>
@endsection
