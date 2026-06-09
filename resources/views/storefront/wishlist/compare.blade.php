@extends('layouts.storefront')
@section('title', 'Bandingkan')
@section('content')
<div class="container">
<h4 class="fw-bold mb-4"><i class="fas fa-balance-scale me-2 text-info"></i> Bandingkan Produk (max 4)</h4>
@if($items->count()>0)
<div class="table-responsive"><table class="table table-bordered"><thead><tr><th></th>@foreach($items as $i)<th class="text-center">{{ $i->product->name }}<br><a href="{{ route('compare.remove', $i) }}" class="btn btn-sm text-danger"><i class="fas fa-times"></i></a></th>@endforeach</tr></thead><tbody>
<tr><td class="fw-semibold">Harga</td>@foreach($items as $i)<td class="text-center fw-bold">Rp {{ number_format($i->product->getEffectivePrice(),0,',','.') }}</td>@endforeach</tr>
<tr><td class="fw-semibold">Toko</td>@foreach($items as $i)<td class="text-center small">{{ $i->product->shop->name ?? '-' }}</td>@endforeach</tr>
<tr><td class="fw-semibold">Kategori</td>@foreach($items as $i)<td class="text-center small">{{ $i->product->category->name ?? '-' }}</td>@endforeach</tr>
<tr><td class="fw-semibold">Brand</td>@foreach($items as $i)<td class="text-center small">{{ $i->product->brand->name ?? '-' }}</td>@endforeach</tr>
<tr><td class="fw-semibold">Stok</td>@foreach($items as $i)<td class="text-center">{{ $i->product->current_stock }}</td>@endforeach</tr>
</tbody></table></div>
@else<div class="text-center py-5"><i class="fas fa-balance-scale fa-4x text-muted mb-3 opacity-25"></i><h5>Belum ada produk</h5><a href="{{ route('products.index') }}" class="btn btn-primary mt-2">Pilih Produk</a></div>@endif
</div>
@endsection
