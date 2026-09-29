@extends('layouts.admin')

@section('title', $employee['name'])

@section('breadcrumb')
    <x-admin.breadcrumb :items="['Sistem', ['label' => 'Pengguna', 'href' => route('admin.users.index')], ['label' => $employee['name']]]" />
@endsection

@section('content')
    <x-admin.page-header :title="$employee['name']" :subtitle="$employee['email'].' · '.$employee['role_label']">
        <x-slot:actions>
            <x-admin.badge :text="$employee['status'] === 'active' ? 'Aktif' : 'Nonaktif'" :color="$employee['status'] === 'active' ? 'success' : 'secondary'" pill />
            @if ($employee['is_super_admin'])
                <x-admin.badge text="Super Admin" color="dark" pill />
            @endif
            <a href="{{ route('admin.users.edit', $employee['id']) }}" class="btn btn-outline-primary btn-sm">Ubah</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if ($employee['is_super_admin'])
        <x-admin.alert type="warning" title="Akun super admin" icon="shield">
            Akun ini melewati setiap pemeriksaan izin. Peran dan izin aksesnya tidak dapat diubah oleh staf biasa.
        </x-admin.alert>
    @endif

    <div class="row g-3">
        <div class="col-lg-5">
            <x-admin.card title="Profil" icon="user" class="mb-3">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <x-admin.avatar :name="$employee['name']" size="lg" />
                    <div>
                        <p class="fw-semibold mb-0">{{ $employee['name'] }}</p>
                        <small class="text-secondary">{{ $employee['email'] }}</small>
                    </div>
                </div>
                <dl class="row small mb-0">
                    <dt class="col-5 text-secondary">Peran</dt>
                    <dd class="col-7 text-end">{{ $employee['role_label'] }}</dd>
                    <dt class="col-5 text-secondary">Status</dt>
                    <dd class="col-7 text-end">{{ ucfirst($employee['status']) }}</dd>
                    <dt class="col-5 text-secondary">Jumlah izin</dt>
                    <dd class="col-7 text-end">{{ number_format($employee['permission_count'], 0, ',', '.') }}</dd>
                    <dt class="col-5 text-secondary">Bergabung</dt>
                    <dd class="col-7 text-end">{{ $employee['joined'] }}</dd>
                </dl>
            </x-admin.card>

            <x-admin.card title="Izin Diberikan" icon="key-square" :subtitle="count($granted).' izin berlaku.'">
                @forelse ($granted as $slug)
                    <x-admin.badge :text="$slug" color="secondary" pill class="me-1 mb-1" />
                @empty
                    <x-admin.empty-state
                        compact
                        icon="key-square"
                        title="Tidak ada izin eksplisit"
                        text="Akun memakai izin bawaan dari kolom perannya."
                    />
                @endforelse
            </x-admin.card>
        </div>

        <div class="col-lg-7">
            <x-admin.card title="Pesanan Terakhir" icon="shopping-bag" subtitle="Pesanan yang dibuat oleh akun ini." flush>
                <div class="table-responsive">
                    <table class="table admin-table mb-0 table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Nomor</th>
                                <th scope="col" class="text-center">Status</th>
                                <th scope="col" class="text-end">Total</th>
                                <th scope="col">Dibuat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($orders as $order)
                                <tr>
                                    <td><a href="{{ $order['url'] }}">{{ $order['order_number'] }}</a></td>
                                    <td class="text-center">
                                        <x-admin.badge :text="$order['order_status_label']" :color="$order['order_status_badge']" pill />
                                    </td>
                                    <td class="text-end fw-semibold">{{ $order['total_formatted'] }}</td>
                                    <td class="text-nowrap">{{ $order['created_at'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <x-admin.empty-state compact icon="shopping-bag" title="Belum ada pesanan" text="Akun ini belum pernah melakukan pemesanan." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>
    </div>
@endsection
