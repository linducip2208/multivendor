@extends('layouts.vendor')
@section('title', 'Kupon Saya')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><x-admin.icon name="ticket" :size="16" class="me-2 text-warning" /> Kupon Toko</h4>
    <a href="{{ route('vendor.coupon.create') }}" class="btn btn-success"><x-admin.icon name="plus" :size="16" class="me-2" /> Buat Kupon</a>
</div>
<x-admin.card :padding="false"><div class="table-responsive"><table class="table table-hover mb-0">
<thead class="table-light"><tr><th>Kode</th><th>Tipe</th><th>Nilai</th><th>Min</th><th>Used</th><th>Berlaku</th><th>Status</th><th>Aksi</th></tr></thead>
<tbody>@forelse($coupons as $c)
<tr><td><code class="fw-bold">{{ $c->code }}</code><br><small>{{ $c->title }}</small></td><td>{{ $c->coupon_type === 'percentage' ? '%' : ($c->coupon_type === 'fixed' ? 'Rp' : 'Ongkir') }}</td><td>{{ $c->coupon_type === 'percentage' ? $c->discount_value.'%' : 'Rp '.number_format($c->discount_value,0,',','.') }}</td><td>Rp {{ number_format($c->min_purchase,0,',','.') }}</td><td>{{ $c->usage_count }}/{{ $c->usage_limit ?? '∞' }}</td><td class="small">{{ $c->start_date?->format('d/m') }} - {{ $c->end_date?->format('d/m') }}</td><td><x-admin.badge :color="$c->status ? 'success' : 'secondary'" :text="$c->status ? 'Aktif' : 'Off'" /></td>
<td><a href="{{ route('vendor.coupon.edit', $c) }}" class="btn btn-sm btn-outline-primary"><x-admin.icon name="edit" :size="16" /></a><form action="{{ route('vendor.coupon.destroy', $c) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger ms-1"><x-admin.icon name="trash" :size="16" /></button></form></td></tr>
@empty<tr><td colspan="8" class="text-center py-5 text-muted">Belum ada kupon</td></tr>@endforelse
</tbody></table></div>@if($coupons->hasPages())<div class="p-3"><x-admin.pagination :paginator="$coupons" /></div>@endif</x-admin.card>
@endsection
