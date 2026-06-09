@extends('layouts.vendor')
@section('title', 'Clearance Sale')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-tags me-2 text-danger"></i> Clearance Sale</h4>
</div>
<div class="card border-0 rounded-4 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Produk</th><th>Harga Normal</th><th>Harga Diskon</th><th>Diskon</th><th>Aksi</th></tr></thead><tbody>
@forelse($products as $p)
<tr><td class="fw-medium">{{ $p->name }}</td><td>Rp {{ number_format($p->price,0,',','.') }}</td><td class="fw-bold text-danger">Rp {{ number_format($p->special_price,0,',','.') }}</td>
<td>
    <form action="{{ route('vendor.clearance.update') }}" method="POST" class="d-flex gap-2">@csrf @method('PUT')
        <input type="hidden" name="product_id" value="{{ $p->id }}">
        <select name="discount_type" class="form-select form-select-sm" style="width:70px"><option value="percentage">%</option><option value="flat">Rp</option></select>
        <input type="number" name="discount_value" class="form-control form-control-sm" style="width:80px" min="0" value="10">
        <button class="btn btn-sm btn-warning">Update</button>
    </form>
</td>
<td><form action="{{ route('vendor.clearance.remove', $p) }}" method="POST">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i> Hapus</button></form></td></tr>
@empty
<tr><td colspan="5" class="text-center py-5 text-muted">Belum ada produk clearance. Set diskon di Produk Saya.</td></tr>
@endforelse
</tbody></table></div>@if($products->hasPages())<div class="p-3">{{ $products->links() }}</div>@endif</div>
@endsection
