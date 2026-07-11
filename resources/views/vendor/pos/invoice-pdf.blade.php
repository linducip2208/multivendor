<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>POS Invoice {{ $order->order_number }}</title>
    <style>
        body{font-family:monospace;font-size:12px;max-width:300px;margin:0 auto;padding:10px}
        .center{text-align:center}
        hr{border-top:1px dashed #999;margin:4px 0}
        .row{display:flex;justify-content:space-between}
        .bold{font-weight:bold}
    </style>
</head>
<body>
    <div class="center">
        <strong style="font-size:14px">{{ $order->shop->name ?? 'Shop' }}</strong><br>
        <small>{{ $order->shop->address ?? '' }}</small>
    </div>
    <hr>
    <div>Order: {{ $order->order_number }}</div>
    <div>Tanggal: {{ $order->created_at->format('d/m/Y H:i') }}</div>
    <div>Kasir: {{ $order->customer->name ?? 'POS' }}</div>
    <hr>
    @foreach($order->items as $item)
    <div>{{ $item->product->name ?? '-' }}</div>
    <div class="row"><span>{{ $item->quantity }} x {{ number_format($item->price,0,',','.') }}</span><span>{{ number_format($item->sub_total,0,',','.') }}</span></div>
    @endforeach
    <hr>
    <div class="row bold" style="font-size:14px"><span>TOTAL</span><span>Rp {{ number_format($order->total,0,',','.') }}</span></div>
    @if($order->discount > 0)<small>Diskon: Rp {{ number_format($order->discount,0,',','.') }}</small>@endif
    <hr>
    <div class="center mt-2">
        <small>--- Terima Kasih ---</small>
    </div>
</body>
</html>
