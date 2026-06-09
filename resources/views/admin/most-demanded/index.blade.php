@extends('layouts.admin')
@section('title','Produk Paling Dicari')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-fire me-2 text-danger"></i> Produk Paling Dicari</h4>
<div class="card border-0 rounded-4 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>#</th><th>Produk</th><th>Toko</th><th>Harga</th><th>Total Dipesan</th><th>Stok</th></tr></thead><tbody>@forelse($products as $i=>$p)<tr><td class="fw-bold text-muted">{{ $i+1 }}</td><td class="fw-medium">{{ $p->name }}</td><td><small>{{ $p->shop->name ?? '' }}</small></td><td>Rp {{ number_format($p->price,0,',','.') }}</td><td class="fw-bold text-success">{{ $p->total_ordered }}</td><td>{{ $p->current_stock }}</td></tr>@empty<tr><td colspan="6" class="text-center py-4 text-muted">Belum ada data</td></tr>@endforelse</tbody></table></div>@if($products->hasPages())<div class="p-3">{{ $products->links() }}</div>@endif</div>
@endsection
