@php($mailLocale = app()->getLocale() === 'en' ? 'en' : 'id')
@if ($mailLocale === 'en')
    <h1>Order shipped</h1>
    <p>Order #{{ $order->order_number }} is on its way.</p>
    @if($order->shipping_tracking_id)<p>Tracking number: <strong>{{ $order->shipping_tracking_id }}</strong></p>@endif
    <p>Thank you for shopping at {{ config('app.name') }}.</p>
@else
    <h1>Pesanan sedang dikirim</h1>
    <p>Pesanan #{{ $order->order_number }} sedang dalam perjalanan.</p>
    @if($order->shipping_tracking_id)<p>Nomor resi: <strong>{{ $order->shipping_tracking_id }}</strong></p>@endif
    <p>Terima kasih telah berbelanja di {{ config('app.name') }}.</p>
@endif
