@extends('layouts.storefront')
@section('title', 'Loyalty Points')
@section('content')
<div class="container" style="max-width:600px;">
<h4 class="fw-bold mb-4"><i class="fas fa-coins me-2 text-warning"></i> Loyalty Points</h4>
<div class="card border-0 shadow-sm rounded-4 mb-4"><div class="card-body text-center p-4">
    <div class="rounded-circle bg-warning bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px;"><i class="fas fa-coins fa-2x text-warning"></i></div>
    <h2 class="fw-bold">{{ number_format($lp->points ?? 0) }}</h2><p class="text-muted">Poin tersedia</p>
    @if(($lp->points ?? 0) >= 100)
    <form action="{{ route('loyalty.redeem') }}" method="POST" class="d-flex gap-2 justify-content-center">@csrf
        <input type="number" name="points" class="form-control" style="width:150px;" min="100" max="{{ $lp->points }}" placeholder="Min 100" required>
        <button class="btn btn-warning"><i class="fas fa-exchange-alt me-1"></i>Tukar ke Wallet</button>
    </form>
    <small class="text-muted mt-1">100 poin = Rp 100</small>
    @else
    <p class="text-muted small">Minimal 100 poin untuk ditukar ke wallet</p>
    @endif
</div></div>
<div class="card border-0 shadow-sm rounded-4"><div class="card-header bg-transparent pt-3"><h6 class="fw-bold mb-0">Riwayat Poin</h6></div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Tgl</th><th>Tipe</th><th>Poin</th><th>Deskripsi</th></tr></thead><tbody>@forelse($transactions as $t)<tr><td class="small">{{ $t->created_at->format('d/m/Y') }}</td><td><span class="badge bg-{{ $t->type==='earn'?'success' : 'danger' }}-subtle">{{ $t->type==='earn'?'+' : '-' }}{{ $t->points }}</span></td><td>{{ $t->points }}</td><td><small>{{ $t->description }}</small></td></tr>@empty<tr><td colspan="4" class="text-center py-4 text-muted">Belum ada aktivitas poin</td></tr>@endforelse</tbody></table></div>@if($transactions->hasPages())<div class="p-3">{{ $transactions->links() }}</div>@endif</div>
<p class="text-muted small mt-2 text-center">Dapatkan poin dari referral + belanja. Kode referral Anda: <code>{{ auth()->user()->referral_code ?? 'N/A' }}</code></p>
</div>
@endsection
