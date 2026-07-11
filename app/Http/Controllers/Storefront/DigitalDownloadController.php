<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\DigitalProductOtp;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class DigitalDownloadController extends Controller
{
    public function verify(Request $request, OrderItem $orderItem)
    {
        if ($orderItem->order->customer_id !== auth()->id()) {
            abort(403);
        }

        $request->validate(['otp' => 'required|string|size:6']);

        $otp = DigitalProductOtp::where('order_item_id', $orderItem->id)
            ->where('otp', strtoupper($request->otp))
            ->where('verified', false)
            ->first();

        if (!$otp) {
            return back()->with('error', 'Kode OTP tidak valid atau sudah digunakan.');
        }

        $otp->verify();

        $product = $orderItem->product;
        if (!$product || !$product->digital_file) {
            return back()->with('error', 'File digital tidak ditemukan.');
        }

        $filePath = storage_path('app/' . $product->digital_file);
        if (!file_exists($filePath)) {
            return back()->with('error', 'File tidak ditemukan di server.');
        }

        return response()->download($filePath, $product->name . '.' . pathinfo($product->digital_file, PATHINFO_EXTENSION));
    }

    public function requestOtp(OrderItem $orderItem)
    {
        if ($orderItem->order->customer_id !== auth()->id()) {
            abort(403);
        }

        if ($orderItem->order->order_status !== 'delivered' && $orderItem->order->order_status !== 'confirmed') {
            return back()->with('error', 'Download hanya tersedia setelah pesanan dikonfirmasi.');
        }

        $existingOtp = DigitalProductOtp::where('order_item_id', $orderItem->id)
            ->where('verified', false)
            ->first();

        if ($existingOtp) {
            return back()->with('success', 'OTP: ' . $existingOtp->otp);
        }

        $otp = DigitalProductOtp::generateForOrderItem($orderItem);

        return back()->with('success', 'Kode OTP Anda: ' . $otp->otp . '. Gunakan untuk download produk digital.');
    }
}
