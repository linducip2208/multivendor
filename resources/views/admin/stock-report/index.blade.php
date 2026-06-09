@extends('layouts.admin')
@section('title', 'Laporan Stok Produk')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-boxes me-2 text-warning"></i> Laporan Stok Produk</h4>
<div class="card border-0 rounded-4 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Produk</th><th>Toko</th><th>SKU</th><th>Stok</th><th>Harga</th><th>Nilai Stok</th></tr></thead><tbody>
@foreach(\App\Models\Product::where('status','approved')->with('shop')->orderBy('current_stock')->paginate(20) as $p)
<tr><td class="fw-medium">{{ $p->name }}</td><td><small>{{ $p->shop->name ?? '' }}</small></td><td>{{ $p->sku ?? '-' }}</td><td><span class="badge bg-{{ $p->current_stock <= 10 ? 'danger' : ($p->current_stock <= 50 ? 'warning' : 'success') }}">{{ $p->current_stock }}</span></td><td>Rp {{ number_format($p->price,0,',','.') }}</td><td class="fw-bold">Rp {{ number_format($p->current_stock * $p->price,0,',','.') }}</td></tr>
@endforeach
</tbody></table></div></div>
{{ \App\Models\Product::where('status','approved')->orderBy('current_stock')->paginate(20)->links() }}
@endsection
