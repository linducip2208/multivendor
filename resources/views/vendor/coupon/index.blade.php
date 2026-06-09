@extends('layouts.vendor')
@section('title', 'Kupon Saya')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-ticket-alt me-2 text-warning"></i> Kupon Toko</h4>
    <a href="{{ route('vendor.coupon.create') }}" class="btn btn-success"><i class="fas fa-plus me-2"></i> Buat Kupon</a>
</div>
<div class="card border-0 rounded-4 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">
<thead class="table-light"><tr><th>Kode</th><th>Tipe</th><th>Nilai</th><th>Min</th><th>Used</th><th>Berlaku</th><th>Status</th><th>Aksi</th></tr></thead>
<tbody>@forelse($coupons as $c)
<tr><td><code class="fw-bold">{{ $c->code }}</code><br><small>{{ $c->title }}</small></td><td>{{ $c->coupon_type === 'percentage' ? '%' : ($c->coupon_type === 'fixed' ? 'Rp' : 'Ongkir') }}</td><td>{{ $c->coupon_type === 'percentage' ? $c->discount_value.'%' : 'Rp '.number_format($c->discount_value,0,',','.') }}</td><td>Rp {{ number_format($c->min_purchase,0,',','.') }}</td><td>{{ $c->usage_count }}/{{ $c->usage_limit ?? '∞' }}</td><td class="small">{{ $c->start_date?->format('d/m') }} - {{ $c->end_date?->format('d/m') }}</td><td><span class="badge bg-{{ $c->status ? 'success' : 'secondary' }}-subtle">{{ $c->status ? 'Aktif' : 'Off' }}</span></td>
<td><a href="{{ route('vendor.coupon.edit', $c) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a><form action="{{ route('vendor.coupon.destroy', $c) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger ms-1"><i class="fas fa-trash"></i></button></form></td></tr>
@empty<tr><td colspan="8" class="text-center py-5 text-muted">Belum ada kupon</td></tr>@endforelse
</tbody></table></div>@if($coupons->hasPages())<div class="p-3">{{ $coupons->links() }}</div>@endif</div>
@endsection
