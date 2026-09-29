<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk {{ $order->order_number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; background: #f1f5f9; }
        .receipt { max-width: 380px; margin: 2rem auto; background: #fff; border-radius: 14px; box-shadow: 0 4px 30px rgba(0,0,0,.08); }
        .receipt__body { padding: 1.5rem; }
        .receipt table { font-size: .85rem; }
        .no-print { display: flex; gap: .5rem; justify-content: center; margin: 1.5rem auto; }
        @media print { body { background: #fff; } .no-print { display: none; } .receipt { box-shadow: none; } }
    </style>
</head>
<body>
<div class="receipt">
    <div class="receipt__body">
        <div class="text-center mb-3">
            <h5 class="fw-bold mb-0">{{ $order->shop?->name ?? config('app.name') }}</h5>
            <small class="text-muted">{{ $order->shop?->address ?? '' }}</small>
        </div>

        <hr>

        <div class="small mb-2">
            <div class="d-flex justify-content-between"><span>Struk</span><span class="fw-medium">{{ $order->order_number }}</span></div>
            <div class="d-flex justify-content-between"><span>Tanggal</span><span>{{ $order->created_at->format('d/m/Y H:i') }}</span></div>
            <div class="d-flex justify-content-between"><span>Kasir</span><span>{{ $order->shop?->vendor?->name ?? auth('vendor')->user()->name }}</span></div>
            <div class="d-flex justify-content-between"><span>Pelanggan</span><span>{{ $order->customer?->name ?? 'Langsung' }}</span></div>
        </div>

        <hr>

        <table class="table table-sm mb-2">
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>
                            <div class="fw-medium">{{ $item->product?->name ?? 'Produk' }}</div>
                            <small class="text-muted">{{ $item->quantity }} × {{ \App\Support\Currency::format($item->price) }}</small>
                        </td>
                        <td class="text-end align-middle text-nowrap">{{ \App\Support\Currency::format($item->sub_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <hr>

        <div class="small">
            <div class="d-flex justify-content-between"><span>Subtotal</span><span>{{ \App\Support\Currency::format($order->sub_total) }}</span></div>
            <div class="d-flex justify-content-between"><span>Pajak</span><span>{{ \App\Support\Currency::format($order->tax) }}</span></div>
            @if ((float) $order->discount > 0)
                <div class="d-flex justify-content-between"><span>Diskon</span><span>-{{ \App\Support\Currency::format($order->discount) }}</span></div>
            @endif
            @if ((float) $order->shipping_cost > 0)
                <div class="d-flex justify-content-between"><span>Ongkir</span><span>{{ \App\Support\Currency::format($order->shipping_cost) }}</span></div>
            @endif
        </div>

        <div class="d-flex justify-content-between fw-bold fs-5 mt-2 pt-2 border-top">
            <span>TOTAL</span>
            <span>{{ \App\Support\Currency::format($order->total) }}</span>
        </div>

        <div class="text-center small text-muted mt-3">
            {{ \App\Support\Currency::config()['name'] }} · {{ ucfirst((string) $order->payment_method) }}
        </div>
    </div>
</div>

<div class="no-print">
    <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Cetak struk</button>
    <a href="{{ route('vendor.pos.held') }}" class="btn btn-outline-secondary btn-sm">Kembali ke kasir</a>
</div>
</body>
</html>
