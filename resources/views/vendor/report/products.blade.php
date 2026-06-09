@extends('layouts.vendor')
@section('title', 'Laporan Produk')
@section('content')
<h4 class="fw-bold mb-1"><i class="fas fa-box me-2 text-success"></i> Laporan Produk</h4>
<p class="text-muted small mb-3">Performa produk toko Anda</p>
<div class="card border-0 rounded-4 shadow-sm"><div class="p-3 border-bottom"><form method="GET"><input type="text" name="search" class="form-control" placeholder="Cari produk..." value="{{ request('search') }}"></form></div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>Produk</th><th>Harga</th><th>Stok</th><th>Terjual</th><th>Pendapatan</th><th>Status</th></tr></thead>
<tbody>@forelse($products as $p)
<tr><td class="fw-medium">{{ Str::limit($p->name, 50) }}</td><td>Rp {{ number_format($p->price,0,',','.') }}</td><td>{{ $p->current_stock }}</td><td class="fw-bold">{{ $p->sold ?? 0 }}</td><td class="fw-bold text-success">Rp {{ number_format($p->revenue ?? 0,0,',','.') }}</td><td><span class="badge bg-{{ $p->status==='approved'?'success' : 'warning' }}-subtle">{{ $p->status }}</span></td></tr>
@empty<tr><td colspan="6" class="text-center py-4 text-muted">Belum ada data</td></tr>@endforelse
</tbody></table></div>@if($products->hasPages())<div class="p-3">{{ $products->links() }}</div>@endif</div>
@endsection
