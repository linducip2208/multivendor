@extends('layouts.vendor')
@section('title', 'Laporan Pesanan')
@section('content')
<h4 class="fw-bold mb-1"><i class="fas fa-shopping-cart me-2 text-primary"></i> Laporan Pesanan</h4>
<p class="text-muted small mb-1">Total Revenue: <span class="fw-bold text-success fs-5">Rp {{ number_format($totalRevenue,0,',','.') }}</span></p>
<div class="card border-0 rounded-4 shadow-sm"><div class="p-3 border-bottom"><form method="GET" class="row g-2"><div class="col-md-2"><input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}"></div><div class="col-md-2"><input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}"></div><div class="col-md-2"><select name="status" class="form-select form-select-sm"><option value="">Semua</option><option value="pending" {{ request('status')==='pending'?'selected' : '' }}>Pending</option><option value="delivered" {{ request('status')==='delivered'?'selected' : '' }}>Delivered</option><option value="canceled" {{ request('status')==='canceled'?'selected' : '' }}>Canceled</option></select></div><div class="col-md-2"><button class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-filter me-1"></i>Filter</button></div></form></div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>Order</th><th>Pelanggan</th><th>Total</th><th>Status</th><th>Tgl</th></tr></thead>
<tbody>@forelse($orders as $o)
<tr><td class="fw-semibold">{{ $o->order_number }}</td><td>{{ $o->customer->name ?? '-' }}</td><td>Rp {{ number_format($o->total,0,',','.') }}</td><td><span class="badge bg-info-subtle text-info">{{ $o->order_status }}</span></td><td class="small">{{ $o->created_at->format('d/m/Y') }}</td></tr>
@empty<tr><td colspan="5" class="text-center py-4 text-muted">Belum ada data</td></tr>@endforelse
</tbody></table></div>@if($orders->hasPages())<div class="p-3">{{ $orders->links() }}</div>@endif</div>
@endsection
