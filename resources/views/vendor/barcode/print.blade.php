<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Barcode</title><style>body{font-family:sans-serif}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;padding:20px}.item{text-align:center;border:1px solid #ddd;padding:15px;border-radius:8px}.item h4{font-size:12px;margin:5px 0}.item .barcode{font-family:'Courier New',monospace;font-size:14px;font-weight:bold}.item .price{font-size:12px;color:#666}</style></head><body>
<div class="grid">@foreach($products as $p)<div class="item"><div class="barcode">*{{ $p->sku ?? $p->id }}*</div><h4>{{ $p->name }}</h4><div class="price">Rp {{ number_format($p->price,0,',','.') }}</div></div>@endforeach</div>
<script>window.onload=function(){window.print()}</script>
</body></html>
