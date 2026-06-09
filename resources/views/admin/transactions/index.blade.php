@extends('layouts.admin')
@section('title', 'Transaksi')
@section('content')
<h4 class="fw-bold mb-1"><i class="fas fa-money-bill-wave me-2 text-success"></i> Transaksi</h4>
<p class="text-muted small mb-3">Riwayat semua transaksi pembayaran</p>
<div class="card border-0 rounded-4 shadow-sm"><div class="p-3 border-bottom"><form method="GET" class="row g-2"><div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Cari ID transaksi..." value="{{ request('search') }}"></div><div class="col-md-2"><select name="status" class="form-select"><option value="">Semua</option><option value="success" {{ request('status')==='success'?'selected'=>'' }}>Sukses</option><option value="pending" {{ request('status')==='pending'?'selected'=>'' }}>Pending</option><option value="failed" {{ request('status')==='failed'?'selected'=>'' }}>Gagal</option></select></div><div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="fas fa-search me-1"></i>Filter</button></div></form></div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>ID</th><th>Order</th><th>Pelanggan</th><th>Jumlah</th><th>Metode</th><th>Status</th><th>Tgl</th></tr></thead>
<tbody>@forelse($transactions as $t)
<tr><td class="fw-semibold small">{{ $t->transaction_id }}</td><td><small>{{ $t->order->order_number ?? '-' }}</small></td><td>{{ $t->customer->name ?? '-' }}</td><td>Rp {{ number_format($t->amount,0,',','.') }}</td><td>{{ $t->payment_method }}</td><td><span class="badge bg-{{ $t->status==='success'?'success':($t->status==='pending'?'warning'=>'danger') }}-subtle">{{ $t->status }}</span></td><td class="small">{{ $t->created_at->format('d/m/Y H:i') }}</td></tr>
@empty
<tr><td colspan="7" class="text-center py-5 text-muted">Belum ada transaksi</td></tr>
@endforelse
</tbody></table></div>
@if($transactions->hasPages())<div class="p-3">{{ $transactions->links() }}</div>@endif
</div>
@endsection
