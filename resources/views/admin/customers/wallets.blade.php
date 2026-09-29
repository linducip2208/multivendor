@extends('layouts.admin')
@section('title', 'Dompet Pelanggan')
@section('content')
<div class="mb-4"><h4 class="fw-bold"><x-admin.icon name="wallet" :size="16" class="me-2" />Dompet Pelanggan</h4></div>
<x-admin.card :padding="false">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th class="text-uppercase small">PELANGGAN</th><th class="text-uppercase small">EMAIL</th><th class="text-uppercase small">SALDO</th><th class="text-uppercase small">MENUNGGU</th><th></th></tr></thead>
            <tbody>
                @forelse($wallets as $w)
                <tr><td><div class="fw-medium">{{ $w->user->name ?? '-' }}</div></td><td>{{ $w->user->email ?? '-' }}</td><td class="fw-bold text-success">Rp {{ number_format($w->balance,0,',','.') }}</td><td>{{ number_format($w->pending_balance,0,',','.') }}</td><td><a href="{{ route('admin.customers.wallet-detail', $w->user) }}" class="btn btn-sm btn-outline-primary">Detail</a></td></tr>
                @empty
                <tr><td colspan="5" class="text-center py-5 text-muted">Tidak ada dompet pelanggan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.card>
<div class="mt-3"><x-admin.pagination :paginator="$wallets" size="sm" /></div>
@endsection
