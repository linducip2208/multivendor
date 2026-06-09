@extends('layouts.vendor')
@section('title', 'POS')
@push('head')
<style>.pos-body{background:#f1f5f9;min-height:calc(100vh - 60px)}.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px}.pos-card{cursor:pointer;background:#fff;border-radius:12px;padding:10px;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.04);transition:all .15s}.pos-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.08);transform:translateY(-2px)}.pos-card.selected{border:2px solid #059669;background:#ecfdf5}</style>
@endpush
@section('content')
<div class="pos-body">
    <div class="row g-0 h-100">
        <div class="col-lg-8 p-3">
            <div class="mb-3"><input type="text" id="posSearch" class="form-control form-control-lg" placeholder="🔍 Cari produk..."></div>
            <div class="product-grid" id="productGrid">
                @foreach($products as $p)
                <div class="pos-card" data-id="{{ $p->id }}" data-name="{{ $p->name }}" data-price="{{ $p->getEffectivePrice() }}" data-stock="{{ $p->current_stock }}">
                    @php $posImg = $p->thumbnail ? (str_starts_with($p->thumbnail,'http') ? $p->thumbnail : asset('storage/'.$p->thumbnail)) : null; @endphp
                    @if($posImg)<img src="{{ $posImg }}" style="width:100%;height:80px;object-fit:contain;border-radius:8px;margin-bottom:4px;" loading="lazy">@endif
                    <div class="fw-semibold small text-truncate">{{ $p->name }}</div>
                    <div class="fw-bold text-success">Rp {{ number_format($p->getEffectivePrice(),0,',','.') }}</div>
                    <small class="text-muted">Stok: {{ $p->current_stock }}</small>
                </div>
                @endforeach
            </div>
        </div>
        <div class="col-lg-4 bg-white border-start p-3 d-flex flex-column">
            <h5 class="fw-bold mb-3"><i class="fas fa-shopping-cart me-2"></i>Keranjang POS</h5>
            <div id="posCart" class="flex-grow-1 overflow-auto mb-3" style="max-height:50vh;">
                <div class="text-center text-muted py-5" id="cartEmpty">Klik produk untuk menambah</div>
            </div>
            <div class="border-top pt-2"><div class="mb-2"><label class="small fw-medium">Nama Customer</label><input type="text" id="customerName" class="form-control form-control-sm" placeholder="Walk-in Customer"></div>
            <div class="mb-2"><label class="small fw-medium">No HP</label><input type="text" id="customerPhone" class="form-control form-control-sm" placeholder="08xxx"></div>
            <div class="mb-2"><label class="small fw-medium">Diskon (Rp)</label><input type="number" id="discountInput" class="form-control form-control-sm" value="0" min="0"></div>
            <div class="mb-2"><label class="small fw-medium">Pembayaran</label><select id="paymentMethod" class="form-select form-select-sm"><option value="cash">Cash</option><option value="qris">QRIS</option><option value="transfer">Transfer</option></select></div></div>
            <div id="cartSummary" class="border-top pt-2 d-none"><div class="d-flex justify-content-between small"><span>Subtotal</span><span id="subtotalDisplay">Rp 0</span></div><div class="d-flex justify-content-between fw-bold fs-5 mt-2"><span>TOTAL</span><span id="totalDisplay">Rp 0</span></div></div>
            <button id="checkoutBtn" class="btn btn-success w-100 btn-lg mt-2" disabled><i class="fas fa-cash-register me-2"></i>Bayar (F8)</button>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function(){
let cart = []; const productGrid = document.getElementById('productGrid');
document.querySelectorAll('.pos-card').forEach(card => { card.addEventListener('click',()=>addToCart(card)); });
document.getElementById('posSearch').addEventListener('input', e => {
    document.querySelectorAll('.pos-card').forEach(c => c.style.display = c.dataset.name.toLowerCase().includes(e.target.value.toLowerCase())?'':'none');
});
function addToCart(card) {
    let id = card.dataset.id, existing = cart.find(i=>i.id==id);
    if(existing){existing.qty++;}else{cart.push({id, name:card.dataset.name, price:parseFloat(card.dataset.price), qty:1});}
    renderCart();
}
function renderCart(){
    let el=document.getElementById('posCart'), sum=0; el.innerHTML=''; if(cart.length==0){el.innerHTML='<div class="text-center text-muted py-5">Klik produk untuk menambah</div>'; document.getElementById('checkoutBtn').disabled=true; document.getElementById('cartSummary').classList.add('d-none');}
    else{cart.forEach((i,j)=>{sum+=i.price*i.qty; el.innerHTML+=`<div class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom"><div class="flex-grow-1"><div class="fw-medium small">${i.name}</div><small class="text-muted">qty:</small><input type="number" class="form-control form-control-sm d-inline" value="${i.qty}" min="1" style="width:60px" onchange="cart[${j}].qty=parseInt(this.value)||1;renderCart()"></div><div class="fw-bold small">Rp ${(i.price*i.qty).toLocaleString('id')}</div><button class="btn btn-sm text-danger" onclick="cart.splice(${j},1);renderCart()"><i class="fas fa-times"></i></button></div>`});}
    let disc=parseFloat(document.getElementById('discountInput').value)||0, total=Math.max(0,sum-disc);
    document.getElementById('subtotalDisplay').textContent='Rp '+sum.toLocaleString('id');
    document.getElementById('totalDisplay').textContent='Rp '+total.toLocaleString('id');
    document.getElementById('checkoutBtn').disabled=cart.length==0;
    document.getElementById('cartSummary').classList.toggle('d-none',cart.length==0);
}
document.getElementById('discountInput').addEventListener('input',renderCart);
document.getElementById('checkoutBtn').addEventListener('click',async()=>{
    if(cart.length==0)return;
    let items=cart.map(i=>({product_id:i.id,quantity:i.qty,price:i.price}));
    let res=await fetch('{{ route("vendor.pos.store") }}',{method:'POST',headers:{'Content-Type'=>'application/json','X-CSRF-TOKEN'=>'{{ csrf_token() }}'},body:JSON.stringify({items,discount:document.getElementById('discountInput').value||0,customer_name:document.getElementById('customerName').value,customer_phone:document.getElementById('customerPhone').value,payment_method:document.getElementById('paymentMethod').value})});
    let data=await res.json();
    if(data.success){alert('Order #'+data.order_number+' berhasil! Total: Rp '+data.total.toLocaleString('id')); cart=[]; renderCart();}
    else{alert('Gagal!');}
});
document.addEventListener('keydown',e=>{if(e.key==='F8'){e.preventDefault(); document.getElementById('checkoutBtn').click();}});
}); // end DOMContentLoaded
</script>
@endpush
