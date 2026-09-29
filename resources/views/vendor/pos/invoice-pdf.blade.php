<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk {{ $order->order_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 0; padding: 18px; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 4px 0; vertical-align: top; }
        th { text-align: left; border-bottom: 1px solid #ddd; }
        .right { text-align: right; }
        .meta td { padding: 1px 0; }
        .total td { border-top: 1px solid #111; font-size: 13px; font-weight: bold; padding-top: 6px; }
        .muted { color: #555; }
        hr { border: none; border-top: 1px dashed #999; margin: 8px 0; }
    </style>
</head>
<body>
    <table class="meta">
        <tr>
            <td style="vertical-align: top;">
                <h1>{{ $order->shop?->name ?? config('app.name') }}</h1>
                <div class="muted">{{ $order->shop?->address ?? '' }}</div>
                <div class="muted">{{ $order->shop?->city ?? '' }}</div>
            </td>
            <td class="right" style="vertical-align: top;">
                <h2>STRUK</h2>
                <div class="muted">{{ $order->order_number }}</div>
                <div class="muted">{{ $order->created_at->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <hr>

    <table class="meta">
        <tr><td class="muted">Kasir</td><td class="right">{{ $order->shop?->vendor?->name ?? auth('vendor')->user()->name }}</td></tr>
        <tr><td class="muted">Pelanggan</td><td class="right">{{ $order->customer?->name ?? 'Walk-in' }}</td></tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Produk</th>
                <th class="right">Qty</th>
                <th class="right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product?->name ?? 'Produk' }}</td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">{{ \App\Support\Currency::format($item->sub_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="meta">
        <tr><td class="muted">Subtotal</td><td class="right">{{ \App\Support\Currency::format($order->sub_total) }}</td></tr>
        <tr><td class="muted">Pajak</td><td class="right">{{ \App\Support\Currency::format($order->tax) }}</td></tr>
        @if ((float) $order->discount > 0)
            <tr><td class="muted">Diskon</td><td class="right">-{{ \App\Support\Currency::format($order->discount) }}</td></tr>
        @endif
        @if ((float) $order->shipping_cost > 0)
            <tr><td class="muted">Ongkir</td><td class="right">{{ \App\Support\Currency::format($order->shipping_cost) }}</td></tr>
        @endif
    </table>

    <table class="total">
        <tr>
            <td>TOTAL</td>
            <td class="right">{{ \App\Support\Currency::format($order->total) }}</td>
        </tr>
    </table>

    <p class="muted" style="text-align:center;margin-top:14px;">
        {{ \App\Support\Currency::config()['name'] }} · {{ ucfirst((string) $order->payment_method) }}<br>
        Terima kasih telah berbelanja.
    </p>
</body>
</html>
