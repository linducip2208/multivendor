@extends('layouts.vendor')
@section('title','Restock Requests')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-redo me-2 text-warning"></i> Restock Requests</h4>
<div class="card border-0 rounded-4 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Produk</th><th>Customer</th><th>Stok Saat Ini</th><th>Status</th><th>Tgl</th></tr></thead><tbody>
@forelse(\App\Models\RestockRequest::whereHas('product',fn($q)=>$q->where('shop_id',auth('vendor')->user()->shop->id))->with(['product','customer'])->latest()->paginate(15) as $r)
<tr><td class="fw-medium">{{ $r->product->name ?? '-' }}</td><td>{{ $r->customer->name ?? '-' }}<br><small>{{ $r->customer->email ?? '' }}</small></td><td><span class="badge bg-danger">{{ $r->product->current_stock ?? 0 }}</span></td><td><span class="badge bg-warning-subtle text-warning">{{ $r->status }}</span></td><td class="small">{{ $r->created_at->format('d/m/Y') }}</td></tr>
@empty<tr><td colspan="5" class="text-center py-5 text-muted">Belum ada restock request</td></tr>@endforelse
</tbody></table></div></div>
@endsection
