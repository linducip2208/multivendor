@extends('layouts.admin')
@section('title', 'Customer Wallets')
@section('content')
<div class="mb-4"><h4 class="fw-bold"><i class="fas fa-wallet me-2"></i>Wallet Pelanggan</h4></div>
<div class="card border-0 rounded-4 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th class="text-uppercase small">PELANGGAN</th><th class="text-uppercase small">EMAIL</th><th class="text-uppercase small">SALDO</th><th class="text-uppercase small">PENDING</th><th></th></tr></thead>
            <tbody>
                @forelse($wallets as $w)
                <tr><td><div class="fw-medium">{{ $w->user->name ?? '-' }}</div></td><td>{{ $w->user->email ?? '-' }}</td><td class="fw-bold text-success">Rp {{ number_format($w->balance,0,',','.') }}</td><td>{{ number_format($w->pending_balance,0,',','.') }}</td><td><a href="{{ route('admin.customers.wallet-detail', $w->user) }}" class="btn btn-sm btn-outline-primary">Detail</a></td></tr>
                @empty
                <tr><td colspan="5" class="text-center py-5 text-muted">Tidak ada wallet pelanggan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $wallets->links('vendor.pagination.bootstrap') }}</div>
@endsection
