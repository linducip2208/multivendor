@extends('layouts.admin')
@section('title', 'Detail Dompet')
@section('content')
<div class="mb-4">
    <a href="{{ route('admin.customers.wallets') }}" class="small"><x-admin.icon name="arrow-left" :size="16" class="me-1" />Kembali</a>
    <h4 class="fw-bold mt-2">{{ $user->name }}</h4>
    <small class="text-muted">{{ $user->email }}</small>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card card-stat"><div class="stat-label">Saldo</div><div class="stat-value text-success">Rp {{ number_format($wallet->balance ?? 0,0,',','.') }}</div></div></div>
    <div class="col-md-4"><div class="card card-stat"><div class="stat-label">Menunggu</div><div class="stat-value text-warning">Rp {{ number_format($wallet->pending_balance ?? 0,0,',','.') }}</div></div></div>
    <div class="col-md-4"><div class="card card-stat"><div class="stat-label">Total Transaksi</div><div class="stat-value">{{ $transactions->total() }}</div></div></div>
</div>

<x-admin.card :padding="false" class="mb-4" style="max-width:500px">
    <div class="card-body">
        <h6 class="fw-bold">Sesuaikan Saldo</h6>
        <form method="POST" action="{{ route('admin.customers.wallet-adjust', $user) }}">
            @csrf
            <div class="mb-2"><input type="number" name="amount" class="form-control" placeholder="Jumlah" step="1" required></div>
            <div class="mb-2"><select name="type" class="form-select"><option value="credit">Kredit (+)</option><option value="debit">Debit (-)</option></select></div>
            <div class="mb-2"><input type="text" name="description" class="form-control" placeholder="Deskripsi"></div>
            <button type="submit" class="btn btn-primary"><x-admin.icon name="refresh" :size="16" class="me-2" />Sesuaikan</button>
        </form>
    </div>
</x-admin.card>

<x-admin.card :padding="false">
    <div class="card-header bg-transparent border-bottom"><h6 class="fw-bold mb-0">Riwayat Transaksi</h6></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th class="text-uppercase small">TIPE</th><th class="text-uppercase small">JUMLAH</th><th class="text-uppercase small">DESKRIPSI</th><th class="text-uppercase small">SALDO</th><th class="text-uppercase small">TANGGAL</th></tr></thead>
            <tbody>
                @forelse($transactions as $tx)
                <tr><td><x-admin.badge :color="$tx->type === 'credit' ? 'success' : 'danger'" :text="$tx->type" /></td><td class="fw-bold">Rp {{ number_format($tx->amount,0,',','.') }}</td><td>{{ $tx->description }}</td><td>Rp {{ number_format($tx->balance_after,0,',','.') }}</td><td><small>{{ $tx->created_at->format('d/m/Y H:i') }}</small></td></tr>
                @empty
                <tr><td colspan="5" class="text-center py-4 text-muted">Tidak ada transaksi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent"><x-admin.pagination :paginator="$transactions" size="sm" /></div>
</x-admin.card>
@endsection
