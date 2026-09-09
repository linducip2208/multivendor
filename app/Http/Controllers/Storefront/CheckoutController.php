<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CouponUsage;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentGroup;
use App\Models\Provider;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Services\CheckoutCalculator;
use App\Services\Payment\PaymentGatewayService;
use App\Services\Shipping\ShippingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function index()
    {
        $cartItems = Cart::where('customer_id', auth()->id())->with(['product.shop', 'variant'])->get();
        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Keranjang kosong.');
        }

        $shops = $cartItems->groupBy(fn ($item) => $item->product->shop_id)->map(fn ($items) => [
            'shop' => $items->first()->product->shop, 'items' => $items,
            'subtotal' => $items->sum(fn ($item) => $item->price * $item->quantity),
        ]);
        $total = $shops->sum('subtotal');
        $addresses = auth()->user()->addresses;
        $paymentGateways = Provider::ofType('payment')->active()->orderBy('sort_order')->get();
        $shippingProviders = Provider::ofType('shipping')->active()->orderBy('sort_order')->get();

        return view('storefront.checkout.index', compact('shops', 'total', 'addresses', 'paymentGateways', 'shippingProviders'));
    }

    public function process(Request $request, CheckoutCalculator $calculator, PaymentGatewayService $payments)
    {
        $validated = $request->validate([
            'address_id' => 'nullable|exists:customer_addresses,id',
            'new_receiver_name' => 'nullable|required_without:address_id|string|max:255',
            'new_receiver_phone' => 'nullable|required_without:address_id|string|max:20',
            'new_address' => 'nullable|required_without:address_id|string|max:500',
            'new_city' => 'nullable|required_without:address_id|string|max:100',
            'new_province' => 'nullable|required_without:address_id|string|max:100',
            'new_shipping_destination_id' => 'nullable|required_without:address_id|string|max:100',
            'shipping_methods' => 'required|array',
            'shipping_methods.*.provider_id' => 'nullable|integer',
            'shipping_methods.*.courier' => 'nullable|string|max:50',
            'shipping_methods.*.service' => 'nullable|string|max:100',
            'shipping_methods.*.destination' => 'nullable|string|max:100',
            'payment_provider_id' => 'required|integer',
            'payment_channel' => 'nullable|array', 'note' => 'nullable|string|max:2000', 'coupon_code' => 'nullable|string|max:50',
        ]);
        $customer = $request->user();
        $shippingMethods = $request->input('shipping_methods', []);
        $provider = Provider::ofType('payment')->active()->find($validated['payment_provider_id']);
        if (! $provider) {
            return back()->withInput()->with('error', 'Metode pembayaran tidak tersedia.');
        }

        try {
            $result = DB::transaction(function () use ($validated, $shippingMethods, $customer, $provider, $calculator, $payments) {
                $address = $this->resolveAddress($validated, $customer->id);
                $cartItems = Cart::where('customer_id', $customer->id)->with(['product.shop', 'variant'])->lockForUpdate()->get();
                if ($cartItems->isEmpty()) {
                    throw ValidationException::withMessages(['cart' => 'Keranjang kosong.']);
                }
                foreach ($shippingMethods as $shopId => $selection) {
                    if (! empty($selection['destination']) && (! $address->shipping_destination_id || $selection['destination'] !== $address->shipping_destination_id)) {
                        throw ValidationException::withMessages(["shipping_methods.{$shopId}.destination" => 'Tujuan pengiriman harus sesuai alamat yang dipilih.']);
                    }
                }
                $quote = $calculator->calculate($customer, $cartItems, $shippingMethods, $validated['coupon_code'] ?? null, $address->toArray());
                $group = PaymentGroup::create([
                    'payment_number' => PaymentGroup::generateNumber(), 'customer_id' => $customer->id, 'provider_id' => $provider->id,
                    'subtotal' => $quote['subtotal'], 'tax' => $quote['tax'], 'shipping_cost' => $quote['shipping'],
                    'discount' => $quote['discount'], 'grand_total' => $quote['grand_total'], 'status' => 'pending',
                ]);
                $orders = collect();
                foreach ($quote['shops'] as $shopQuote) {
                    $selection = $shippingMethods[$shopQuote->shop->id] ?? [];
                    $order = Order::create([
                        'payment_group_id' => $group->id, 'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id,
                        'shop_id' => $shopQuote->shop->id, 'coupon_code' => $quote['coupon']?->code,
                        'coupon_discount' => $shopQuote->couponDiscount, 'sub_total' => $shopQuote->subtotal, 'tax' => $shopQuote->tax,
                        'shipping_cost' => $shopQuote->shipping, 'discount' => 0,
                        'total' => max(0, $shopQuote->subtotal + $shopQuote->tax + $shopQuote->shipping - $shopQuote->couponDiscount),
                        'shipping_method' => $selection['courier'] ?? null, 'shipping_service' => $selection['service'] ?? null,
                        'shipping_address' => $address->only(['label', 'receiver_name', 'receiver_phone', 'address', 'city', 'province', 'postal_code']),
                        'payment_method' => $provider->api_format, 'payment_status' => 'unpaid', 'order_status' => 'pending', 'note' => $validated['note'] ?? null,
                    ]);
                    $group->orders()->attach($order->id, ['amount' => $order->total]);
                    $order->statusHistory()->create(['status' => 'pending', 'changed_by' => $customer->id, 'note' => 'Pesanan dibuat.']);
                    foreach ($shopQuote->shopLines as $line) {
                        OrderItem::create(['order_id' => $order->id, 'product_id' => $line->product->id, 'product_variant_id' => $line->variant?->id,
                            'quantity' => $line->quantity, 'price' => $line->price, 'tax' => $line->line_total * ($line->tax_rate / 100),
                            'discount' => 0, 'sub_total' => $line->line_total, 'variant_detail' => $line->variant?->variant]);
                        if ($line->variant) {
                            $line->variant->decrement('stock', $line->quantity);
                        } else {
                            $line->product->decrement('current_stock', $line->quantity);
                        }
                    }
                    Transaction::create(['transaction_id' => 'TRX-'.$order->order_number, 'payment_group_id' => $group->id, 'order_id' => $order->id,
                        'customer_id' => $customer->id, 'shop_id' => $order->shop_id, 'amount' => $order->total, 'admin_commission' => 0,
                        'vendor_amount' => 0, 'payment_method' => $this->transactionPaymentMethod($provider), 'status' => 'pending']);
                    $orders->push($order);
                }
                if ($quote['coupon']) {
                    $quote['coupon']->increment('usage_count');
                    CouponUsage::create(['coupon_id' => $quote['coupon']->id, 'customer_id' => $customer->id, 'order_id' => $orders->first()->id, 'discount_amount' => $quote['discount']]);
                }
                $paymentResult = $payments->createPayment($provider, [
                    'order_id' => $group->payment_number, 'amount' => $group->grand_total,
                    'channel' => $validated['payment_channel'][$provider->id] ?? 'default',
                    'customer' => ['name' => $customer->name, 'email' => $customer->email, 'phone' => $customer->phone],
                    'items' => $this->paymentItems($orders), 'success_url' => route('orders.index'),
                    'callback_url' => route('webhook.payment', $provider),
                ]);
                if (! ($paymentResult['success'] ?? false)) {
                    throw ValidationException::withMessages(['payment_provider_id' => 'Gateway pembayaran tidak dapat membuat transaksi. Silakan pilih metode lain atau coba kembali.']);
                }
                $group->update(['gateway_reference' => $paymentResult['transaction_id'] ?? $paymentResult['invoice_id'] ?? $paymentResult['reference'] ?? null, 'gateway_response' => $paymentResult['raw'] ?? []]);
                Cart::where('customer_id', $customer->id)->delete();

                return ['group' => $group, 'payment' => $paymentResult];
            });

            return ! empty($result['payment']['redirect_url'])
                ? redirect()->away($result['payment']['redirect_url'])
                : redirect()->route('orders.index')->with('success', 'Pesanan dibuat. Selesaikan pembayaran Anda.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Checkout failed', ['customer_id' => $customer->id, 'exception' => $e::class]);

            return back()->withInput()->with('error', 'Checkout tidak dapat diproses. Silakan coba kembali.');
        }
    }

    public function shippingCost(Request $request, ShippingService $shipping)
    {
        $data = $request->validate(['shop_id' => 'required|integer|exists:shops,id', 'destination' => 'required|string|max:100', 'courier' => 'required|string|max:50', 'provider_id' => 'required|integer']);
        $provider = Provider::ofType('shipping')->active()->find($data['provider_id']);
        if (! $provider) {
            return response()->json(['success' => false, 'message' => 'Provider pengiriman tidak tersedia.'], 422);
        }
        $cart = Cart::where('customer_id', $request->user()->id)->whereHas('product', fn ($q) => $q->where('shop_id', $data['shop_id']))->with('product')->get();
        $origin = SystemSetting::get("shop_shipping_origin_{$data['shop_id']}") ?: SystemSetting::get('shipping_origin');
        if (! $origin || $cart->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Data pengiriman belum lengkap.'], 422);
        }

        return response()->json($shipping->getShippingRates($provider, ['origin' => $origin, 'destination' => $data['destination'], 'courier' => $data['courier'], 'weight' => $cart->sum(fn ($item) => max(1, (int) $item->product->weight) * $item->quantity)]));
    }

    private function resolveAddress(array $data, int $customerId): CustomerAddress
    {
        if (! empty($data['address_id'])) {
            return CustomerAddress::where('customer_id', $customerId)->findOrFail($data['address_id']);
        }

        return CustomerAddress::create(['customer_id' => $customerId, 'label' => $data['new_label'] ?? 'Rumah', 'receiver_name' => $data['new_receiver_name'],
            'receiver_phone' => $data['new_receiver_phone'], 'address' => $data['new_address'], 'city' => $data['new_city'], 'province' => $data['new_province'],
            'postal_code' => $data['new_postal_code'] ?? null, 'shipping_destination_id' => $data['new_shipping_destination_id'], 'is_default' => ! CustomerAddress::where('customer_id', $customerId)->exists()]);
    }

    private function transactionPaymentMethod(Provider $provider): string
    {
        return match (true) {
            str_contains($provider->api_format, 'midtrans') => 'midtrans', str_contains($provider->api_format, 'xendit') => 'xendit',
            default => 'transfer',
        };
    }

    private function paymentItems($orders): array
    {
        $items = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $items[] = ['id' => 'ITEM-'.$item->id, 'name' => mb_substr($item->product->name, 0, 50), 'price' => (int) round($item->price), 'quantity' => $item->quantity];
            }
            if ((float) $order->shipping_cost > 0) {
                $items[] = ['id' => 'SHIP-'.$order->id, 'name' => 'Biaya pengiriman', 'price' => (int) round($order->shipping_cost), 'quantity' => 1];
            }
            if ((float) $order->tax > 0) {
                $items[] = ['id' => 'TAX-'.$order->id, 'name' => 'Pajak', 'price' => (int) round($order->tax), 'quantity' => 1];
            }
            if ((float) $order->coupon_discount > 0) {
                $items[] = ['id' => 'DISC-'.$order->id, 'name' => 'Diskon', 'price' => -(int) round($order->coupon_discount), 'quantity' => 1];
            }
        }

        return $items;
    }
}
