<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class TrackOrderController extends Controller
{
    /**
     * Guest-safe tracking form + authed quick lookup via ?order_number=.
     *
     * Guests are never resolved by order number alone (enumeration-safe);
     * they must use lookup() below with order_number + email/phone proof.
     */
    public function show(Request $request)
    {
        $order = null;

        if ($request->filled('order_number') && $request->user()) {
            $order = Order::where('order_number', $request->input('order_number'))
                ->where('customer_id', $request->user()->id)
                ->with(['items.product', 'statusHistory', 'shop'])
                ->first();
        }

        return view('storefront.track-order', compact('order'));
    }

    /**
     * Guest-supported lookup: order_number + email or phone verification.
     *
     * Scoped so a valid order number alone never leaks another
     * customer's order; the contact proof must match the order owner
     * (users.email/phone) or the stored shipping_address email/phone.
     * Safe to sit behind `throttle` middleware (validation errors
     * redirect back, no exception leak on missing order).
     */
    public function lookup(Request $request)
    {
        $validated = $request->validate([
            'order_number' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:180'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:30'],
        ]);

        $order = Order::where('order_number', $validated['order_number'])
            ->with(['customer', 'items.product', 'statusHistory', 'shop'])
            ->first();

        if ($order && $this->contactMatches($order, $validated['email'] ?? null, $validated['phone'] ?? null, $request)) {
            return view('storefront.track-order', compact('order'));
        }

        return back()
            ->withInput($request->only('order_number', 'email', 'phone'))
            ->withErrors(['order_number' => 'Pesanan tidak ditemukan atau data verifikasi tidak cocok.'])
            ->with('error', 'Pesanan tidak ditemukan atau data verifikasi tidak cocok.');
    }

    private function contactMatches(Order $order, ?string $email, ?string $phone, Request $request): bool
    {
        // Signed-in owner may always re-open their own order.
        if ($request->user() && (int) $order->customer_id === (int) $request->user()->id) {
            return true;
        }

        $candidates = [
            $order->customer?->email,
            $order->customer?->phone,
        ];

        $shipping = $order->shipping_address;
        if (is_string($shipping)) {
            $shipping = json_decode($shipping, true) ?: [];
        }
        if (is_array($shipping)) {
            $candidates[] = $shipping['email'] ?? null;
            $candidates[] = $shipping['phone'] ?? null;
            $candidates[] = $shipping['receiver_phone'] ?? null;
            $candidates[] = $shipping['customer_email'] ?? null;
        }

        $normalisePhone = static fn (?string $v): string => preg_replace('/\D+/', '', (string) $v);

        if ($email !== null && $email !== '') {
            foreach ($candidates as $candidate) {
                if (is_string($candidate) && strcasecmp(trim($candidate), trim($email)) === 0) {
                    return true;
                }
            }
        }

        if ($phone !== null && $phone !== '') {
            $want = $normalisePhone($phone);
            if ($want !== '') {
                foreach ($candidates as $candidate) {
                    if ($normalisePhone(is_string($candidate) ? $candidate : null) !== '' && $normalisePhone(is_string($candidate) ? $candidate : null) === $want) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
