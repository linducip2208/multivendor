<h1>Pesanan sedang dikirim</h1>
<p>Pesanan #{{ $order->order_number }} sedang dalam perjalanan.</p>
@if($order->shipping_tracking_id)<p>Nomor resi: <strong>{{ $order->shipping_tracking_id }}</strong></p>@endif
