<a href="{{ route('products.show', $product->slug) }}" class="text-decoration-none">
<div class="card product-card h-100 position-relative">
    @if($product->getDiscountPercentage())
    <span class="position-absolute top-0 start-0 badge bg-danger m-2 z-1">-{{ $product->getDiscountPercentage() }}%</span>
    @endif
    @if($product->featured)
    <span class="position-absolute top-0 end-0 badge bg-warning m-2 z-1"><i class="fas fa-star"></i></span>
    @endif
    <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height:180px;">
        @if($product->thumbnail)<img src="{{ asset('storage/'.$product->thumbnail) }}" class="w-100 h-100" style="object-fit:contain;" loading="lazy">@else<i class="fas fa-box fa-3x text-muted opacity-25"></i>@endif
    </div>
    <div class="card-body p-3">
        <div class="small text-muted mb-1">{{ $product->shop->name ?? '' }}</div>
        <h6 class="card-title small fw-semibold text-dark mb-2 line-clamp-2">{{ $product->name }}</h6>
        @if($product->reviews->avg('rating'))
        <div class="text-warning small mb-1">★ {{ number_format($product->reviews->avg('rating'),1) }} ({{ $product->reviews->count() }})</div>
        @endif
        <div class="d-flex justify-content-between align-items-center">
            <div><span class="fw-bold text-primary">Rp {{ number_format($product->getEffectivePrice(), 0, ',', '.') }}</span>
            @if($product->getDiscountPercentage())<br><small class="text-muted text-decoration-line-through">Rp {{ number_format($product->price, 0, ',', '.') }}</small>@endif</div>
        </div>
    </div>
</div></a>
