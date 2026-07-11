@extends('layouts.vendor')
@section('title', 'Held Orders')
@section('content')
<div class="mb-4">
    <h4 class="fw-bold"><i class="fas fa-pause-circle me-2"></i>Hold Orders (POS)</h4>
    <a href="{{ route('vendor.pos.index') }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-plus me-1"></i>POS Baru</a>
</div>

<div class="card border-0 rounded-4 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-uppercase small">ORDER</th>
                    <th class="text-uppercase small">CUSTOMER</th>
                    <th class="text-uppercase small">ITEMS</th>
                    <th class="text-uppercase small">TOTAL</th>
                    <th class="text-uppercase small">TANGGAL</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                <tr>
                    <td><strong>{{ $order->order_number }}</strong></td>
                    <td>{{ str_replace('POS: ', '', $order->note ?? '-') }}</td>
                    <td>{{ $order->items->sum('quantity') }} item</td>
                    <td class="fw-bold">Rp {{ number_format($order->total,0,',','.') }}</td>
                    <td><small>{{ $order->created_at->format('d/m/Y H:i') }}</small></td>
                    <td>
                        <form method="POST" action="{{ route('vendor.pos.resume', $order) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-success btn-sm"><i class="fas fa-play me-1"></i>Lanjut</button>
                        </form>
                        <a href="{{ route('vendor.pos.print', $order) }}" class="btn btn-outline-secondary btn-sm" target="_blank"><i class="fas fa-print"></i></a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-5 text-muted">Tidak ada hold order.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $orders->links('vendor.pagination.bootstrap') }}</div>
@endsection
