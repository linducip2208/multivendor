@extends('layouts.vendor')
@section('title', 'Stok Menipis')
@section('content')
<h4 class="fw-bold mb-1"><i class="fas fa-exclamation-triangle me-2 text-warning"></i> Stok Menipis</h4>
<p class="text-muted small mb-3">Produk dengan stok ≤ {{ $threshold }}</p>
<div class="card border-0 rounded-4 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Produk</th><th>SKU</th><th>Stok</th><th>Harga</th><th>Status</th></tr></thead><tbody>@forelse($products as $p)<tr><td class="fw-medium">{{ $p->name }}</td><td>{{ $p->sku ?? '-' }}</td><td><span class="badge bg-danger">{{ $p->current_stock }}</span></td><td>Rp {{ number_format($p->price,0,',','.') }}</td><td><a href="{{ route('vendor.products.edit', $p) }}" class="btn btn-sm btn-outline-warning"><i class="fas fa-edit me-1"></i>Update Stok</a></td></tr>@empty<tr><td colspan="5" class="text-center py-5 text-muted">Semua stok aman</td></tr>@endforelse</tbody></table></div>@if($products->hasPages())<div class="p-3">{{ $products->links() }}</div>@endif</div>
@endsection
