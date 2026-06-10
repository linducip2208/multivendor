@extends('layouts.storefront')
@section('title','Social Feed')
@section('content')
<div class="container" style="max-width:500px;">
<h4 class="fw-bold mb-4 text-center"><i class="fas fa-fire me-2 text-danger"></i>Feed</h4>
@foreach($feeds as $f)
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
    @if($f->video_url)<video src="{{ $f->video_url }}" class="w-100" controls style="max-height:400px;object-fit:cover;"></video>@elseif($f->product->thumbnail)<img src="{{ url('img/'.$f->product->thumbnail) }}" class="w-100" style="max-height:400px;object-fit:cover;">@endif
    <div class="card-body p-3">
        <div class="d-flex align-items-center gap-2 mb-2"><i class="fas fa-store text-muted"></i><span class="fw-semibold small">{{ $f->shop->name }}</span></div>
        <p class="small mb-2">{{ $f->caption }}</p>
        <div class="d-flex justify-content-between align-items-center">
            <a href="{{ route('products.show',$f->product->slug) }}" class="btn btn-primary btn-sm"><i class="fas fa-shopping-cart me-1"></i>Beli Rp {{ number_format($f->product->getEffectivePrice(),0,',','.') }}</a>
            <small class="text-muted"><i class="fas fa-eye me-1"></i>{{ $f->views }} <i class="fas fa-heart ms-2 me-1"></i>{{ $f->likes }}</small>
        </div>
    </div>
</div>
@endforeach
{{ $feeds->links() }}
</div>
@endsection
