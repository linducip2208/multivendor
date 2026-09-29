@extends('layouts.admin')
@section('title', 'Pelanggan')
@section('content')
<h4 class="fw-bold mb-1"><x-admin.icon name="users" :size="16" class="me-2 text-primary" /> Pelanggan</h4>
<p class="text-muted small mb-3">Data semua customer terdaftar</p>
<x-admin.card :padding="false"><div class="p-3 border-bottom"><form method="GET" class="row g-2"><div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Cari nama atau email..." value="{{ request('search') }}"></div><div class="col-md-2"><button class="btn btn-outline-primary w-100"><x-admin.icon name="search" :size="16" class="me-1" />Cari</button></div></form></div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>Nama</th><th>Email</th><th>HP</th><th>Pesanan</th><th>Status</th><th>Tgl Daftar</th><th>Aksi</th></tr></thead>
<tbody>@forelse($customers as $c)
<tr><td class="fw-medium">{{ $c->name }}</td><td>{{ $c->email }}</td><td>{{ $c->phone ?? '-' }}</td><td><x-admin.badge color="info" :text="$c->orders_count" /></td><td><x-admin.badge :color="$c->status==='active'?'success' : 'secondary'" :text="$c->status" /></td><td class="small">{{ $c->created_at->format('d/m/Y') }}</td><td><a href="{{ route('admin.customers.show', $c) }}" class="btn btn-sm btn-outline-primary"><x-admin.icon name="eye" :size="16" /></a></td></tr>
@empty
<tr><td colspan="7" class="text-center py-5 text-muted">Belum ada pelanggan</td></tr>
@endforelse
</tbody></table></div>
@if($customers->hasPages())<div class="p-3"><x-admin.pagination :paginator="$customers" /></div>@endif
</x-admin.card>
@endsection
