@extends('layouts.admin')
@section('title', 'Wallet Kurir')
@section('content')
<h4 class="fw-bold mb-1"><x-admin.icon name="wallet" :size="16" class="me-2 text-success" /> Wallet: {{ $deliveryMan->name }}</h4>
<p class="text-muted small mb-3">Saldo: <span class="fw-bold fs-5 text-success">Rp {{ number_format($wallet->balance ?? 0,0,',','.') }}</span></p>
<x-admin.card :padding="false"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Tgl</th><th>Tipe</th><th>Jumlah</th><th>Deskripsi</th></tr></thead><tbody>@forelse($transactions as $t)<tr><td class="small">{{ $t->created_at->format('d/m/Y H:i') }}</td><td><x-admin.badge :color="$t->type==='credit'?'success' : 'danger'" :text="$t->type" /></td><td>Rp {{ number_format($t->amount,0,',','.') }}</td><td>{{ $t->description ?? '-' }}</td></tr>@empty<tr><td colspan="4" class="text-center py-4 text-muted">Belum ada transaksi</td></tr>@endforelse</tbody></table></div></x-admin.card>
@endsection
