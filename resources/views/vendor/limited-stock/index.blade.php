@extends('layouts.vendor')
@section('title', 'Stok Menipis')
@section('content')
<h4 class="fw-bold mb-1"><x-admin.icon name="alert-triangle" :size="16" class="me-2 text-warning" /> Stok Menipis</h4>
<p class="text-muted small mb-3">Produk dengan stok ≤ {{ $threshold }}</p>
<x-admin.card :padding="false"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Produk</th><th>SKU</th><th>Stok</th><th>Harga</th><th>Status</th></tr></thead><tbody>@forelse($products as $p)<tr><td class="fw-medium">{{ $p->name }}</td><td>{{ $p->sku ?? '-' }}</td><td><x-admin.badge color="danger" :text="$p->current_stock" /></td><td>Rp {{ number_format($p->price,0,',','.') }}</td><td><a href="{{ route('vendor.products.edit', $p) }}" class="btn btn-sm btn-outline-warning"><x-admin.icon name="edit" :size="16" class="me-1" />Perbarui Stok</a></td></tr>@empty<tr><td colspan="5" class="text-center py-5 text-muted">Semua stok aman</td></tr>@endforelse</tbody></table></div>@if($products->hasPages())<div class="p-3"><x-admin.pagination :paginator="$products" /></div>@endif</x-admin.card>
@endsection
