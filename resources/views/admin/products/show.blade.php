@extends('layouts.admin')
@section('title', 'Detail Produk')
@section('content')
<div class="mb-4"><a href="{{ route('admin.products.index') }}" class="small"><i class="fas fa-arrow-left me-1"></i>Kembali</a><h4 class="fw-bold mt-2">{{ $product->name }}</h4></div>
<div class="row g-4">
    <div class="col-lg-8"><div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
        <h6 class="fw-bold mb-3">Info Produk</h6>
        <div class="row g-2 small"><div class="col-md-6"><span class="text-muted">SKU:</span> {{ $product->sku ?? '-' }}</div><div class="col-md-6"><span class="text-muted">Brand:</span> {{ $product->brand->name ?? '-' }}</div><div class="col-md-6"><span class="text-muted">Kategori:</span> {{ $product->category->name ?? '-' }}</div><div class="col-md-6"><span class="text-muted">Toko:</span> {{ $product->shop->name ?? '-' }}</div><div class="col-md-6"><span class="text-muted">Tipe:</span> {{ $product->product_type }}</div><div class="col-md-6"><span class="text-muted">Satuan:</span> {{ $product->unit ?? 'pcs' }}</div></div>
        <hr>
        <div class="d-flex gap-4 mb-3"><div><small class="text-muted">Harga</small><br><span class="fw-bold fs-5 text-primary">Rp {{ number_format($product->price,0,',','.') }}</span></div><div><small class="text-muted">Diskon</small><br>Rp {{ number_format($product->special_price??0,0,',','.') }}</div><div><small class="text-muted">Stok</small><br>{{ $product->current_stock }}</div></div>
        @if($product->description)<div class="mt-3"><small class="text-muted">Deskripsi</small><p>{!! nl2br(e($product->description)) !!}</p></div>@endif
    </div></div></div>
    <div class="col-lg-4">
        <div class="card border-0 rounded-4 shadow-sm mb-3"><div class="card-body"><h6 class="fw-bold mb-3">Status</h6>
            @php $b=['pending'=>'warning','approved'=>'success','suspended'=>'danger']; @endphp
            <span class="badge bg-{{ $b[$product->status] }}-subtle text-{{ $b[$product->status] }} fs-6">{{ ucfirst($product->status) }}</span>
            <div class="mt-3">
                @if($product->status !== 'approved')<form action="{{ route('admin.products.update-status', $product) }}" method="POST" class="d-inline">@csrf @method('PUT')<input type="hidden" name="status" value="approved"><button class="btn btn-success btn-sm w-100 mb-2"><i class="fas fa-check me-1"></i>Approve</button></form>@endif
                @if($product->status !== 'suspended')<form action="{{ route('admin.products.update-status', $product) }}" method="POST" class="d-inline">@csrf @method('PUT')<input type="hidden" name="status" value="suspended"><button class="btn btn-outline-warning btn-sm w-100"><i class="fas fa-pause me-1"></i>Suspend</button></form>@endif
            </div>
        </div></div>
        @if($product->variants->count() > 0)
        <div class="card border-0 rounded-4 shadow-sm"><div class="card-body"><h6 class="fw-bold mb-3">Varian ({{ $product->variants->count() }})</h6>
            @foreach($product->variants as $v)<div class="border rounded-3 p-2 mb-2 small"><span class="fw-medium">{{ $v->variant }}</span> · Rp {{ number_format($v->price,0,',','.') }} · Stok: {{ $v->stock }}</div>@endforeach
        </div></div>
        @endif
        @if($product->reviews->count() > 0)
        <div class="card border-0 rounded-4 shadow-sm"><div class="card-body"><h6 class="fw-bold mb-3">Ulasan ({{ $product->reviews->count() }})</h6>
            @foreach($product->reviews->take(3) as $r)<div class="border-bottom pb-2 mb-2 small"><span class="fw-medium">{{ $r->customer->name ?? '-' }}</span> <span class="text-warning">@for($i=1;$i<=5;$i++)<i class="fas fa-star{{ $i<=$r->rating?'':'-o' }}"></i>@endfor</span><br>{{ $r->comment }}</div>@endforeach
        </div></div>
        @endif
    </div>
</div>
@endsection
