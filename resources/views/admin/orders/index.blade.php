@extends('layouts.admin')
@section('title', 'Pesanan')
@section('content')
<h4 class="fw-bold mb-1"><i class="fas fa-shopping-cart me-2 text-primary"></i> Pesanan</h4>
<p class="text-muted small mb-3">Kelola semua pesanan marketplace</p>

<div class="row g-3 mb-4">
    @php $st = ['pending'=>'warning','confirmed'=>'info','processing'=>'primary','shipped'=>'indigo','delivered'=>'success','canceled'=>'danger']; @endphp
    @foreach($st as $k=>$c)
    <div class="col-4 col-md-2"><a href="?status={{ $k }}" class="text-decoration-none"><div class="card border-0 rounded-4 shadow-sm text-center p-3 {{ request('status')===$k?'border border-2 border-'.$c:'' }}"><div class="fw-bold fs-5 text-{{ $c }}">{{ $statusCounts[$k]??0 }}</div><small class="text-muted">{{ ucfirst($k) }}</small></div></a></div>
    @endforeach
</div>

<div class="card border-0 rounded-4 shadow-sm">
    <div class="p-3 border-bottom"><form method="GET" class="row g-2">
        <div class="col-md-3"><input type="text" name="search" class="form-control" placeholder="Cari nomor order..." value="{{ request('search') }}"></div>
        <div class="col-md-2"><select name="payment" class="form-select"><option value="">Payment</option><option value="unpaid" {{ request('payment')==='unpaid'?'selected'=>'' }}>Unpaid</option><option value="paid" {{ request('payment')==='paid'?'selected'=>'' }}>Paid</option></select></div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="fas fa-search me-1"></i>Filter</button></div>
    </form></div>
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Order</th><th>Pelanggan</th><th>Toko</th><th>Total</th><th>Bayar</th><th>Status</th><th>Tgl</th><th>Aksi</th></tr></thead>
        <tbody>
            @forelse($orders as $o)
            <tr>
                <td class="fw-semibold">{{ $o->order_number }}</td>
                <td>{{ $o->customer->name ?? '-' }}</td>
                <td><small>{{ $o->shop->name ?? '-' }}</small></td>
                <td>Rp {{ number_format($o->total,0,',','.') }}</td>
                <td><span class="badge bg-{{ $o->payment_status==='paid'?'success'=>'warning' }}-subtle">{{ $o->payment_status }}</span></td>
                <td><span class="badge bg-{{ $st[$o->order_status] }}-subtle text-{{ $st[$o->order_status] }}">{{ ucfirst($o->order_status) }}</span></td>
                <td class="small">{{ $o->created_at->format('d/m/Y') }}</td>
                <td><a href="{{ route('admin.orders.show', $o) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center py-5 text-muted">Belum ada pesanan</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @if($orders->hasPages())<div class="p-3">{{ $orders->links() }}</div>@endif
</div>
@endsection
