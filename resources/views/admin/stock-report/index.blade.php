@extends('layouts.admin')
@section('title', 'Laporan Stok Produk')
@section('content')
<h4 class="fw-bold mb-3"><x-admin.icon name="box" :size="16" class="me-2 text-warning" /> Laporan Stok Produk</h4>
<x-admin.card :padding="false"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Produk</th><th>Toko</th><th>SKU</th><th>Stok</th><th>Harga</th><th>Nilai Stok</th></tr></thead><tbody>
@php($stockProducts = \App\Models\Product::where('status','approved')->with('shop')->orderBy('current_stock')->paginate(20))
@forelse($stockProducts as $p)
<tr><td class="fw-medium">{{ $p->name }}</td><td><small>{{ $p->shop->name ?? '' }}</small></td><td>{{ $p->sku ?? '-' }}</td><td><x-admin.badge :color="$p->current_stock <= 10 ? 'danger' : ($p->current_stock <= 50 ? 'warning' : 'success')" :text="$p->current_stock" /></td><td>Rp {{ number_format($p->price,0,',','.') }}</td><td class="fw-bold">Rp {{ number_format($p->current_stock * $p->price,0,',','.') }}</td></tr>
@empty
<tr><td colspan="6" class="text-center py-4 text-muted">Belum ada produk</td></tr>
@endforelse
</tbody></table></div>@if($stockProducts->hasPages())<div class="p-3"><x-admin.pagination :paginator="$stockProducts" /></div>@endif</x-admin.card>
@endsection
