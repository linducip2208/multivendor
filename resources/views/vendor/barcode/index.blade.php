@extends('layouts.vendor')
@section('title', 'Barcode Produk')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-barcode me-2 text-dark"></i> Barcode Produk</h4>
<form method="GET" action="{{ route('vendor.barcode.print') }}" target="_blank" class="mb-3">
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-3"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th><input type="checkbox" id="selectAll"></th><th>Produk</th><th>SKU</th><th>Harga</th></tr></thead><tbody>
@foreach($products as $p)
<tr><td><input type="checkbox" name="ids[]" value="{{ $p->id }}" class="product-check"></td><td class="fw-medium">{{ $p->name }}</td><td>{{ $p->sku ?? '-' }}</td><td>Rp {{ number_format($p->price,0,',','.') }}</td></tr>
@endforeach
</tbody></table></div>
<div class="mt-3"><button class="btn btn-dark"><i class="fas fa-print me-2"></i>Cetak Barcode</button></div>
</div></div>
</form>
<script>document.getElementById('selectAll').addEventListener('change',function(){document.querySelectorAll('.product-check').forEach(c=>c.checked=this.checked)});</script>
@endsection
