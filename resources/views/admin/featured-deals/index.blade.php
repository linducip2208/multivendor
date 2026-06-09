@extends('layouts.admin')
@section('title', 'Featured Deal')
@section('content')
<h4 class="fw-bold mb-1"><i class="fas fa-star me-2 text-warning"></i> Featured Deal</h4>
<p class="text-muted small mb-3">Produk unggulan yang tampil di halaman depan</p>
<div class="row g-4"><div class="col-lg-5"><div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4"><form action="{{ route('admin.featured-deals.store') }}" method="POST">@csrf
<div class="mb-3"><label class="fw-medium">Pilih Produk (max 20)</label><select name="product_ids[]" class="form-select" multiple size="15" required>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->shop->name ?? '' }})</option>@endforeach</select></div>
<button class="btn btn-warning w-100"><i class="fas fa-star me-1"></i>Jadikan Featured</button>
</form></div></div></div>
<div class="col-lg-7"><div class="card border-0 rounded-4 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Produk</th><th>Toko</th><th>Harga</th><th></th></tr></thead><tbody>@forelse($featured as $p)<tr><td class="fw-medium">{{ $p->name }}</td><td><small>{{ $p->shop->name ?? '' }}</small></td><td>Rp {{ number_format($p->price,0,',','.') }}</td><td><form action="{{ route('admin.featured-deals.remove', $p) }}" method="POST">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Hapus</button></form></td></tr>@empty<tr><td colspan="4" class="text-center py-4 text-muted">Belum ada produk featured</td></tr>@endforelse</tbody></table></div></div></div></div>
@endsection
