@extends('layouts.vendor')
@section('title', 'Refund')
@section('content')
<h4 class="fw-bold mb-1"><i class="fas fa-undo me-2 text-warning"></i> Refund</h4>
<p class="text-muted small mb-3">Permintaan refund dari customer</p>
<div class="card border-0 rounded-4 shadow-sm"><div class="p-3 border-bottom"><form method="GET" class="row g-2"><div class="col-md-2"><select name="status" class="form-select form-select-sm"><option value="">Semua</option><option value="requested" {{ request('status')==='requested'?'selected' : '' }}>Requested</option><option value="approved" {{ request('status')==='approved'?'selected' : '' }}>Approved</option><option value="rejected" {{ request('status')==='rejected'?'selected' : '' }}>Rejected</option></select></div><div class="col-md-2"><button class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-filter me-1"></i>Filter</button></div></form></div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Order</th><th>Produk</th><th>Qty</th><th>Harga</th><th>Alasan</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
@forelse($refunds as $r)
<tr><td><small>{{ $r->order->order_number ?? '-' }}</small></td><td>{{ $r->product->name ?? '-' }}</td><td>{{ $r->quantity }}</td><td>Rp {{ number_format($r->price,0,',','.') }}</td><td><small>{{ $r->refund_reason ?? '-' }}</small></td><td><span class="badge bg-{{ $r->refund_status==='requested'?'warning':($r->refund_status==='approved'?'success' : 'danger') }}-subtle">{{ $r->refund_status }}</span></td>
<td>@if($r->refund_status==='requested')<div class="d-flex gap-1"><form action="{{ route('vendor.refund.update', $r) }}" method="POST">@csrf @method('PUT')<input type="hidden" name="status" value="approved"><button class="btn btn-sm btn-success">Setuju</button></form><form action="{{ route('vendor.refund.update', $r) }}" method="POST">@csrf @method('PUT')<input type="hidden" name="status" value="rejected"><button class="btn btn-sm btn-danger">Tolak</button></form></div>@endif</td></tr>
@empty<tr><td colspan="7" class="text-center py-5 text-muted">Belum ada refund</td></tr>@endforelse
</tbody></table></div>@if($refunds->hasPages())<div class="p-3">{{ $refunds->links() }}</div>@endif</div>
@endsection
