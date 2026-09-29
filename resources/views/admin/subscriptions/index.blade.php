@extends('layouts.admin')
@section('title', 'Vendor Subscriptions')
@section('content')
<div class="mb-4"><h4 class="fw-bold"><x-admin.icon name="list" :size="16" class="me-2" />Langganan Vendor</h4></div>
<x-admin.card :padding="false">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th class="text-uppercase small">VENDOR</th><th class="text-uppercase small">TOKO</th><th class="text-uppercase small">PAKET</th><th class="text-uppercase small">JUMLAH</th><th class="text-uppercase small">STATUS</th><th class="text-uppercase small">BERAKHIR</th></tr></thead>
            <tbody>
                @forelse($subscriptions as $sub)
                <tr>
                    <td>{{ $sub->vendor->name ?? '-' }}</td>
                    <td>{{ $sub->shop->name ?? '-' }}</td>
                    <td><x-admin.badge color="primary" :text="$sub->plan->name ?? '-'" /></td>
                    <td>Rp {{ number_format($sub->amount_paid,0,',','.') }}</td>
                    <td><x-admin.badge :color="$sub->status === 'active' ? 'success' : ($sub->status === 'canceled' ? 'danger' : 'warning')" :text="$sub->status" /></td>
                    <td><small>{{ $sub->ends_at?->format('d/m/Y') ?? '-' }}</small></td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada langganan vendor.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.card>
<div class="mt-3"><x-admin.pagination :paginator="$subscriptions" size="sm" /></div>
@endsection
