@extends('layouts.admin')
@section('title', 'Pelanggan')
@section('content')
<h4 class="fw-bold mb-1"><i class="fas fa-users me-2 text-primary"></i> Pelanggan</h4>
<p class="text-muted small mb-3">Data semua customer terdaftar</p>
<div class="card border-0 rounded-4 shadow-sm"><div class="p-3 border-bottom"><form method="GET" class="row g-2"><div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Cari nama atau email..." value="{{ request('search') }}"></div><div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="fas fa-search me-1"></i>Cari</button></div></form></div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>Nama</th><th>Email</th><th>HP</th><th>Pesanan</th><th>Status</th><th>Tgl Daftar</th><th>Aksi</th></tr></thead>
<tbody>@forelse($customers as $c)
<tr><td class="fw-medium">{{ $c->name }}</td><td>{{ $c->email }}</td><td>{{ $c->phone ?? '-' }}</td><td><span class="badge bg-info-subtle text-info">{{ $c->orders_count }}</span></td><td><span class="badge bg-{{ $c->status==='active'?'success' : 'secondary' }}-subtle">{{ $c->status }}</span></td><td class="small">{{ $c->created_at->format('d/m/Y') }}</td><td><a href="{{ route('admin.customers.show', $c) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td></tr>
@empty
<tr><td colspan="7" class="text-center py-5 text-muted">Belum ada pelanggan</td></tr>
@endforelse
</tbody></table></div>
@if($customers->hasPages())<div class="p-3">{{ $customers->links() }}</div>@endif
</div>
@endsection
