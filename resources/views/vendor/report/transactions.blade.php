@extends('layouts.vendor')
@section('title', 'Laporan Transaksi')
@section('content')
<h4 class="fw-bold mb-1"><i class="fas fa-money-bill-wave me-2 text-warning"></i> Laporan Transaksi</h4>
<p class="text-muted small mb-1">Total Diterima: <span class="fw-bold text-success fs-5">Rp {{ number_format($totalSuccess,0,',','.') }}</span></p>
<div class="card border-0 rounded-4 shadow-sm"><div class="p-3 border-bottom"><form method="GET" class="row g-2"><div class="col-md-2"><select name="status" class="form-select form-select-sm"><option value="">Semua</option><option value="success" {{ request('status')==='success'?'selected'=>'' }}>Sukses</option><option value="pending" {{ request('status')==='pending'?'selected'=>'' }}>Pending</option></select></div><div class="col-md-2"><button class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-filter me-1"></i>Filter</button></div></form></div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>ID</th><th>Order</th><th>Jumlah</th><th>Komisi Admin</th><th>Diterima</th><th>Status</th><th>Tgl</th></tr></thead>
<tbody>@forelse($transactions as $t)
<tr><td class="small">{{ $t->transaction_id }}</td><td>{{ $t->order->order_number ?? '-' }}</td><td>Rp {{ number_format($t->amount,0,',','.') }}</td><td>Rp {{ number_format($t->admin_commission,0,',','.') }}</td><td class="fw-bold text-success">Rp {{ number_format($t->vendor_amount,0,',','.') }}</td><td><span class="badge bg-{{ $t->status==='success'?'success'=>'warning' }}-subtle">{{ $t->status }}</span></td><td class="small">{{ $t->created_at->format('d/m/Y') }}</td></tr>
@empty<tr><td colspan="7" class="text-center py-4 text-muted">Belum ada transaksi</td></tr>@endforelse
</tbody></table></div>@if($transactions->hasPages())<div class="p-3">{{ $transactions->links() }}</div>@endif</div>
@endsection
