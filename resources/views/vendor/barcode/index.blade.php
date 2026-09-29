@extends('layouts.vendor')
@section('title', 'Barkode Produk')
@section('content')
<h4 class="fw-bold mb-3"><x-admin.icon name="barcode" :size="16" class="me-2 text-dark" /> Barkode Produk</h4>
<form method="GET" action="{{ route('vendor.barcode.print') }}" target="_blank" class="mb-3">
<x-admin.card :padding="false"><div class="card-body p-3"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th><input type="checkbox" id="selectAll"></th><th>Produk</th><th>SKU</th><th>Harga</th></tr></thead><tbody>
@foreach($products as $p)
<tr><td><input type="checkbox" name="ids[]" value="{{ $p->id }}" class="product-check"></td><td class="fw-medium">{{ $p->name }}</td><td>{{ $p->sku ?? '-' }}</td><td>Rp {{ number_format($p->price,0,',','.') }}</td></tr>
@endforeach
</tbody></table></div>
<div class="mt-3"><button class="btn btn-dark"><x-admin.icon name="printer" :size="16" class="me-2" />Cetak Barkode</button></div>
</div></x-admin.card>
</form>
<script>document.getElementById('selectAll').addEventListener('change',function(){document.querySelectorAll('.product-check').forEach(c=>c.checked=this.checked)});</script>
@endsection
