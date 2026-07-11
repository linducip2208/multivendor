@extends('layouts.storefront')
@section('title', 'Rate Delivery')
@section('content')
<div class="container py-5" style="max-width:500px">
    <h4 class="fw-bold mb-3">⭐ Rating Kurir</h4>
    <div class="card border-0 rounded-4 shadow-sm">
        <div class="card-body">
            <p class="small text-muted">Order: {{ $order->order_number }}</p>
            <form method="POST" action="{{ route('delivery.rate.store', $order) }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-medium">Rating (1-5)</label>
                    <div class="d-flex gap-2 fs-4" id="starRating">
                        @for($i=1;$i<=5;$i++)
                        <span class="star" data-rating="{{ $i }}" style="cursor:pointer;color:{{ ($existing->rating ?? 0) >= $i ? '#f59e0b' : '#d1d5db' }}">★</span>
                        @endfor
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" value="{{ $existing->rating ?? 5 }}">
                </div>
                <div class="mb-3"><label class="form-label fw-medium">Review</label><textarea name="review" class="form-control" rows="3" placeholder="Bagaimana pengalaman Anda?">{{ $existing->review ?? '' }}</textarea></div>
                <button type="submit" class="btn btn-primary w-100">Kirim Rating</button>
            </form>
        </div>
    </div>
</div>
@push('scripts')
<script>
document.querySelectorAll('#starRating .star').forEach(s => {
    s.addEventListener('click',()=>{
        let r=parseInt(s.dataset.rating);
        document.getElementById('ratingInput').value = r;
        document.querySelectorAll('#starRating .star').forEach((el,i)=>el.style.color=i<r?'#f59e0b':'#d1d5db');
    });
    s.addEventListener('mouseenter',()=>{
        let r=parseInt(s.dataset.rating);
        document.querySelectorAll('#starRating .star').forEach((el,i)=>el.style.color=i<r?'#f59e0b':'#d1d5db');
    });
});
document.getElementById('starRating').addEventListener('mouseleave',()=>{
    let r=parseInt(document.getElementById('ratingInput').value);
    document.querySelectorAll('#starRating .star').forEach((el,i)=>el.style.color=i<r?'#f59e0b':'#d1d5db';
});
</script>
@endpush
@endsection
