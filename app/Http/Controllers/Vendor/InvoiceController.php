<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;

class InvoiceController extends Controller
{
    public function show(Order $order)
    {
        $shop = auth('vendor')->user()->shop;
        if ($order->shop_id !== $shop->id) abort(403);
        $order->load(['items.product', 'customer', 'shop']);
        return view('vendor.invoice.show', compact('order'));
    }

    public function download(Order $order)
    {
        $shop = auth('vendor')->user()->shop;
        if ($order->shop_id !== $shop->id) abort(403);
        $order->load(['items.product', 'customer', 'shop']);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('vendor.invoice.pdf', compact('order'));
        return $pdf->download("invoice-{$order->order_number}.pdf");
    }
}
