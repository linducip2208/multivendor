@extends('layouts.admin')
@section('title', 'Deal of the Day')
@section('content')
<h4 class="fw-bold mb-1"><i class="fas fa-calendar-check me-2 text-danger"></i> Deal of the Day</h4>
<p class="text-muted small mb-3">Pilih 1 produk untuk diskon spesial hari ini</p>
<div class="row g-4"><div class="col-lg-5"><div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4"><form action="{{ route('admin.deals.store') }}" method="POST">@csrf
<div class="mb-3"><label class="fw-medium">Produk</label><select name="product_id" class="form-select" required><option value="">Pilih produk...</option>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }} (Rp {{ number_format($p->price,0,',','.') }}) — {{ $p->shop->name ?? '' }}</option>@endforeach</select></div>
<div class="row g-2 mb-3"><div class="col-6"><label class="fw-medium">Tipe Diskon</label><select name="discount_type" class="form-select"><option value="percentage">%</option><option value="flat">Rp</option></select></div><div class="col-6"><label class="fw-medium">Nilai</label><input type="number" name="discount_value" class="form-control" min="0" value="10"></div></div>
<button class="btn btn-danger w-100"><i class="fas fa-save me-1"></i>Simpan</button>
</form></div></div></div>
<div class="col-lg-7"><div class="card border-0 rounded-4 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Produk</th><th>Toko</th><th>Harga Asli</th><th>Diskon</th><th>Tanggal</th><th></th></tr></thead><tbody>@forelse($deals as $d)<tr><td class="fw-medium">{{ $d->product->name ?? '-' }}</td><td><small>{{ $d->product->shop->name ?? '' }}</small></td><td>Rp {{ number_format($d->product->price??0,0,',','.') }}</td><td>{{ $d->discount_type==='percentage' ? $d->discount_value.'%' : 'Rp '.number_format($d->discount_value,0,',','.') }}</td><td>{{ $d->date }}</td><td><form action="{{ route('admin.deals.destroy', $d) }}" method="POST">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form></td></tr>@empty<tr><td colspan="6" class="text-center py-4 text-muted">Belum ada deal</td></tr>@endforelse</tbody></table></div></div></div></div>
@endsection
