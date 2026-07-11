@extends('layouts.vendor')
@section('title', 'Cash Collect')
@section('content')
<div class="mb-4"><h4 class="fw-bold"><i class="fas fa-money-bill-wave me-2"></i>Cash Collect (COD)</h4></div>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card card-stat"><div class="stat-label">Pending Collection</div><div class="stat-value text-danger">Rp {{ number_format($totalPending,0,',','.') }}</div></div></div>
    <div class="col-md-4"><div class="card card-stat"><div class="stat-label">Sudah Dikumpul</div><div class="stat-value text-success">Rp {{ number_format($totalCollected,0,',','.') }}</div></div></div>
    <div class="col-md-4"><div class="card card-stat"><div class="stat-label">Total Transaksi</div><div class="stat-value">{{ $collects->total() }}</div></div></div>
</div>
<div class="card border-0 rounded-4 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th class="text-uppercase small">ORDER</th><th class="text-uppercase small">JUMLAH</th><th class="text-uppercase small">STATUS</th><th class="text-uppercase small">TANGGAL</th><th></th></tr></thead>
            <tbody>
                @forelse($collects as $c)
                <tr><td><strong>{{ $c->order->order_number ?? '-' }}</strong></td><td class="fw-bold">Rp {{ number_format($c->amount,0,',','.') }}</td><td><span class="badge bg-{{ $c->collected ? 'success' : 'warning' }}-subtle">{{ $c->collected ? 'Lunas' : 'Pending' }}</span></td><td><small>{{ $c->created_at->format('d/m/Y H:i') }}</small></td><td>@if(!$c->collected)<form method="POST" action="{{ route('vendor.cash-collect.mark', $c) }}">@csrf <button class="btn btn-success btn-sm">Tandai Lunas</button></form>@else<small class="text-muted">{{ $c->collected_at?->format('d/m/Y H:i') }}</small>@endif</td></tr>
                @empty
                <tr><td colspan="5" class="text-center py-5 text-muted">Tidak ada cash collect.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $collects->links('vendor.pagination.bootstrap') }}</div>
@endsection
