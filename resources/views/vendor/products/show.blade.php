@extends('layouts.vendor')
@section('title', 'Detail Produk')
@section('content')
<div class="mb-4"><a href="{{ route('vendor.products.index') }}" class="small"><i class="fas fa-arrow-left me-1"></i>Kembali</a><h4 class="fw-bold mt-2">{{ $product->name }}</h4></div>
<div class="row g-4">
    <div class="col-md-8"><div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
        @if($product->thumbnail)<div class="text-center mb-3"><img src="{{ url('img/'.$product->thumbnail) }}" class="rounded-4" style="max-width:100%;max-height:350px;object-fit:cover;"></div>@endif
        <div class="row g-3 small"><div class="col-md-6"><span class="text-muted">SKU:</span> {{ $product->sku ?? '-' }}</div><div class="col-md-6"><span class="text-muted">Kategori:</span> {{ $product->category->name ?? '-' }}</div><div class="col-md-6"><span class="text-muted">Brand:</span> {{ $product->brand->name ?? '-' }}</div><div class="col-md-6"><span class="text-muted">Tipe:</span> {{ $product->product_type }}</div><div class="col-md-6"><span class="text-muted">Satuan:</span> {{ $product->unit ?? 'pcs' }}</div><div class="col-md-6"><span class="text-muted">Min/Max Qty:</span> {{ $product->min_qty }}/{{ $product->max_qty }}</div></div>
        <hr>
        <div class="d-flex gap-4 mb-3"><div><small class="text-muted">Harga</small><br><span class="fw-bold fs-5 text-success">Rp {{ number_format($product->price,0,',','.') }}</span></div><div><small class="text-muted">Diskon</small><br>Rp {{ number_format($product->special_price??0,0,',','.') }}</div><div><small class="text-muted">Stok</small><br>{{ $product->current_stock }}</div><div><small class="text-muted">Pajak</small><br>{{ $product->tax }}%</div></div>
        @if($product->description)<div class="mt-3"><small class="text-muted">Deskripsi</small><div class="lh-lg">{!! $product->description !!}</div></div>@endif
    </div></div></div>
    <div class="col-md-4">
        <div class="card border-0 rounded-4 shadow-sm mb-3"><div class="card-body"><h6 class="fw-bold mb-3">Status</h6>
            @php $b=['pending'=>'warning','approved'=>'success','suspended'=>'danger']; @endphp
            <span class="badge bg-{{ $b[$product->status] }}-subtle text-{{ $b[$product->status] }} px-3 py-2">{{ ucfirst($product->status) }}</span>
        </div></div>
        @if($product->variants->count() > 0)
        <div class="card border-0 rounded-4 shadow-sm"><div class="card-body"><h6 class="fw-bold mb-3">Varian ({{ $product->variants->count() }})</h6>
            @foreach($product->variants as $v)<div class="border rounded-3 p-2 mb-2 small"><span class="fw-medium">{{ $v->variant }}</span> · Rp {{ number_format($v->price,0,',','.') }} · Stok: {{ $v->stock }}</div>@endforeach
        </div></div>
        @endif
        <div class="mt-2"><a href="{{ route('vendor.products.edit', $product) }}" class="btn btn-outline-primary w-100"><i class="fas fa-edit me-1"></i>Edit Produk</a></div>
    </div>
</div>
@endsection
