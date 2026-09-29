@extends('layouts.admin')

@section('title', 'Manajemen Vendor')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><x-admin.icon name="store" :size="16" class="me-2 text-primary" /> Manajemen Vendor</h4>
        <p class="text-muted small mb-0">Kelola semua toko dan vendor di platform</p>
    </div>
    <a href="{{ route('admin.vendors.create') }}" class="btn btn-primary">
        <x-admin.icon name="plus" :size="16" class="me-2" /> Tambah Vendor
    </a>
</div>

<x-admin.card :padding="false">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <form method="GET" class="row g-2">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Cari vendor atau toko..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Ditolak</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100"><x-admin.icon name="search" :size="16" class="me-1" /> Filter</button>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Toko</th>
                        <th>Vendor</th>
                        <th>Kontak</th>
                        <th>Komisi</th>
                        <th>Status</th>
                        <th>Bergabung</th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shops as $shop)
                    <tr>
                        <td class="ps-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                                    <x-admin.icon name="store" :size="16" class="text-primary small" />
                                </div>
                                <div>
                                    <div class="fw-semibold">{{ $shop->name }}</div>
                                    <small class="text-muted">{{ $shop->slug }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-medium">{{ $shop->vendor->name ?? '-' }}</div>
                            <small class="text-muted">{{ $shop->vendor->email ?? '-' }}</small>
                        </td>
                        <td>
                            <small>{{ $shop->phone ?? '-' }}</small><br>
                            <small class="text-muted">{{ Str::limit($shop->address, 30) }}</small>
                        </td>
                        <td>
                            @if($shop->commission_type === 'percentage')
                                <x-admin.badge color="info">{{ $shop->commission_value }}%</x-admin.badge>
                            @else
                                <x-admin.badge color="secondary">Rp {{ number_format($shop->commission_value, 0, ',', '.') }}</x-admin.badge>
                            @endif
                        </td>
                        <td>
                            @php
                                $badges = ['pending' => 'warning', 'active' => 'success', 'suspended' => 'danger', 'rejected' => 'dark'];
                            @endphp
                            <x-admin.badge :color="$badges[$shop->status] ?? 'secondary'" :text="ucfirst($shop->status)" />
                        </td>
                        <td class="small text-muted">{{ $shop->created_at->format('d/m/Y') }}</td>
                        <td class="text-end pe-3">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown">
                                    <x-admin.icon name="menu" :size="16" />
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="{{ route('admin.vendors.show', $shop) }}"><x-admin.icon name="eye" :size="16" class="me-2" /> Detail</a></li>
                                    <li><a class="dropdown-item" href="{{ route('admin.vendors.edit', $shop) }}"><x-admin.icon name="edit" :size="16" class="me-2" /> Edit</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    @if($shop->status !== 'active')
                                    <li>
                                        <form action="{{ route('admin.vendors.update-status', $shop) }}" method="POST">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="status" value="active">
                                            <button class="dropdown-item text-success"><x-admin.icon name="check" :size="16" class="me-2" /> Aktifkan</button>
                                        </form>
                                    </li>
                                    @endif
                                    @if($shop->status !== 'suspended')
                                    <li>
                                        <form action="{{ route('admin.vendors.update-status', $shop) }}" method="POST">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="status" value="suspended">
                                            <button class="dropdown-item text-warning"><x-admin.icon name="minus" :size="16" class="me-2" /> Suspended</button>
                                        </form>
                                    </li>
                                    @endif
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="{{ route('admin.vendors.destroy', $shop) }}" method="POST" onsubmit="return confirm('Hapus vendor ini?')">
                                            @csrf @method('DELETE')
                                            <button class="dropdown-item text-danger"><x-admin.icon name="trash" :size="16" class="me-2" /> Hapus</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center py-5 text-muted">
                        <x-admin.icon name="store" :size="48" class="mb-3 opacity-25" />
                        <p>Belum ada vendor terdaftar</p>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($shops->hasPages())
        <div class="p-3 border-top">
            <x-admin.pagination :paginator="$shops" />
        </div>
        @endif
    </div>
</x-admin.card>
@endsection
