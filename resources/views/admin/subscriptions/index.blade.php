@extends('layouts.admin')
@section('title', 'Vendor Subscriptions')
@section('content')
<div class="mb-4"><h4 class="fw-bold"><i class="fas fa-list me-2"></i>Langganan Vendor</h4></div>
<div class="card border-0 rounded-4 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th class="text-uppercase small">VENDOR</th><th class="text-uppercase small">TOKO</th><th class="text-uppercase small">PAKET</th><th class="text-uppercase small">JUMLAH</th><th class="text-uppercase small">STATUS</th><th class="text-uppercase small">BERAKHIR</th></tr></thead>
            <tbody>
                @forelse($subscriptions as $sub)
                <tr>
                    <td>{{ $sub->vendor->name ?? '-' }}</td>
                    <td>{{ $sub->shop->name ?? '-' }}</td>
                    <td><span class="badge bg-primary-subtle text-primary">{{ $sub->plan->name ?? '-' }}</span></td>
                    <td>Rp {{ number_format($sub->amount_paid,0,',','.') }}</td>
                    <td><span class="badge bg-{{ $sub->status === 'active' ? 'success' : ($sub->status === 'canceled' ? 'danger' : 'warning') }}-subtle">{{ $sub->status }}</span></td>
                    <td><small>{{ $sub->ends_at?->format('d/m/Y') ?? '-' }}</small></td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada langganan vendor.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $subscriptions->links('vendor.pagination.bootstrap') }}</div>
@endsection
