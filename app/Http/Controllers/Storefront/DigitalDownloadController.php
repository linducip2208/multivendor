<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\DigitalProductOtp;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

class DigitalDownloadController extends Controller
{
    public function verify(Request $request, OrderItem $orderItem)
    {
        if ($orderItem->order->customer_id !== auth()->id()) {
            abort(403);
        }
        if ($orderItem->order->payment_status !== 'paid' || !in_array($orderItem->order->order_status, ['confirmed', 'processing', 'shipped', 'delivered'], true) || $orderItem->product?->product_type !== 'digital') {
            return back()->with('error', 'Download belum tersedia untuk pesanan ini.');
        }

        $rateKey = 'digital-otp:'.auth()->id().':'.$orderItem->id;
        if (RateLimiter::tooManyAttempts($rateKey, 5)) return back()->with('error', 'Terlalu banyak percobaan OTP. Coba lagi beberapa menit.');

        $request->validate(['otp' => 'required|string|size:6']);

        $otp = DigitalProductOtp::where('order_item_id', $orderItem->id)
            ->where('otp', strtoupper($request->otp))
            ->where('verified', false)
            ->first();

        if (!$otp) {
            RateLimiter::hit($rateKey, 300);
            return back()->with('error', 'Kode OTP tidak valid atau sudah digunakan.');
        }

        $otp->verify();

        $product = $orderItem->product;
        if (!$product || !$product->digital_file) {
            return back()->with('error', 'File digital tidak ditemukan.');
        }

        if (!Storage::disk('private')->exists($product->digital_file)) {
            return back()->with('error', 'File tidak ditemukan di server.');
        }
        RateLimiter::clear($rateKey);
        return Storage::disk('private')->download($product->digital_file, $product->name . '.' . pathinfo($product->digital_file, PATHINFO_EXTENSION));
    }

    public function requestOtp(OrderItem $orderItem)
    {
        if ($orderItem->order->customer_id !== auth()->id()) {
            abort(403);
        }

        if ($orderItem->order->payment_status !== 'paid' || !in_array($orderItem->order->order_status, ['confirmed', 'processing', 'shipped', 'delivered'], true) || $orderItem->product?->product_type !== 'digital') {
            return back()->with('error', 'Download hanya tersedia setelah pesanan dikonfirmasi.');
        }

        $existingOtp = DigitalProductOtp::where('order_item_id', $orderItem->id)
            ->where('verified', false)
            ->first();

        if ($existingOtp) return back()->with('success', 'OTP aktif telah dikirim sebelumnya.');

        $otp = DigitalProductOtp::generateForOrderItem($orderItem);

        // Delivery via notification/email is intentionally asynchronous; never expose the OTP in UI.
        \Illuminate\Support\Facades\Log::info('Digital download OTP generated', ['order_item_id' => $orderItem->id, 'customer_id' => auth()->id()]);
        return back()->with('success', 'Kode OTP download berhasil dibuat. Periksa kanal notifikasi akun Anda.');
    }
}
