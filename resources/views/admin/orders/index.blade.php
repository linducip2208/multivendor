@extends('layouts.admin')
@section('title', 'Pesanan')
@section('content')
<h4 class="fw-bold mb-1"><x-admin.icon name="shopping-cart" :size="16" class="me-2 text-primary" /> Pesanan</h4>
<p class="text-muted small mb-3">Kelola semua pesanan marketplace</p>

<div class="row g-3 mb-4">
    @php $st = ['pending'=>'warning','confirmed'=>'info','processing'=>'primary','shipped'=>'indigo','delivered'=>'success','canceled'=>'danger']; @endphp
    @foreach($st as $k=>$c)
    <div class="col-4 col-md-2"><a href="?status={{ $k }}" class="text-decoration-none"><x-admin.card :padding="false" class="text-center p-3 {{ request('status')===$k?'border border-2 border-'.$c:'' }}"><div class="fw-bold fs-5 text-{{ $c }}">{{ $statusCounts[$k]??0 }}</div><small class="text-muted">{{ ucfirst($k) }}</small></x-admin.card></a></div>
    @endforeach
</div>

<x-admin.card :padding="false">
    <div class="p-3 border-bottom"><form method="GET" class="row g-2">
        <div class="col-md-3"><input type="text" name="search" class="form-control" placeholder="Cari nomor pesanan..." value="{{ request('search') }}"></div>
        <div class="col-md-2"><select name="payment" class="form-select"><option value="">Pembayaran</option><option value="unpaid" {{ request('payment')==='unpaid'?'selected' : '' }}>Belum Dibayar</option><option value="paid" {{ request('payment')==='paid'?'selected' : '' }}>Dibayar</option></select></div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100"><x-admin.icon name="search" :size="16" class="me-1" />Filter</button></div>
    </form></div>
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Pesanan</th><th>Pelanggan</th><th>Toko</th><th>Total</th><th>Bayar</th><th>Status</th><th>Tgl</th><th>Aksi</th></tr></thead>
        <tbody>
            @forelse($orders as $o)
            <tr>
                <td class="fw-semibold">{{ $o->order_number }}</td>
                <td>{{ $o->customer->name ?? '-' }}</td>
                <td><small>{{ $o->shop->name ?? '-' }}</small></td>
                <td>Rp {{ number_format($o->total,0,',','.') }}</td>
                <td><x-admin.badge :color="$o->payment_status==='paid'?'success' : 'warning'" :text="$o->payment_status" /></td>
                <td><x-admin.badge :color="$st[$o->order_status]" :text="ucfirst($o->order_status)" /></td>
                <td class="small">{{ $o->created_at->format('d/m/Y') }}</td>
                <td><a href="{{ route('admin.orders.show', $o) }}" class="btn btn-sm btn-outline-primary"><x-admin.icon name="eye" :size="16" /></a></td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center py-5 text-muted">Belum ada pesanan</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @if($orders->hasPages())<div class="p-3"><x-admin.pagination :paginator="$orders" /></div>@endif
</x-admin.card>
@endsection
