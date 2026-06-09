<p>Halo {{ $order->customer->name }},</p>
<p>Pesanan <strong>#{{ $order->order_number }}</strong> telah dikonfirmasi.</p>
<p>Total: <strong>Rp {{ number_format($order->total,0,',','.') }}</strong></p>
<p>Status: {{ $order->order_status }}</p>
<p>Terima kasih telah berbelanja di {{ config('app.name') }}.</p>
