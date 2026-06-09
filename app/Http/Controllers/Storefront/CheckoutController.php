<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Provider;
use App\Models\Transaction;
use App\Services\Payment\PaymentGatewayService;
use App\Services\Shipping\ShippingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function index()
    {
        $cartItems = Cart::where('customer_id', auth()->id())
            ->with(['product.shop', 'variant'])
            ->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Keranjang kosong.');
        }

        $groupedByShop = $cartItems->groupBy(fn ($i) => $i->product->shop_id);
        $shops = [];
        foreach ($groupedByShop as $shopId => $items) {
            $shop = $items->first()->product->shop;
            $subtotal = $items->sum(fn ($i) => $i->price * $i->quantity);
            $shops[] = ['shop' => $shop, 'items' => $items, 'subtotal' => $subtotal];
        }

        $total = collect($shops)->sum('subtotal');
        $addresses = auth()->user()->addresses;
        $paymentGateways = Provider::ofType('payment')->active()->orderBy('sort_order')->get();
        $shippingProviders = Provider::ofType('shipping')->active()->orderBy('sort_order')->get();

        return view('storefront.checkout.index', compact(
            'shops', 'total', 'addresses', 'paymentGateways', 'shippingProviders'
        ));
    }

    public function process(Request $request)
    {
        $request->validate([
            'address_id' => 'nullable|exists:customer_addresses,id',
            'new_receiver_name' => 'nullable|required_without:address_id|string|max:255',
            'new_receiver_phone' => 'nullable|required_without:address_id|string|max:20',
            'new_address' => 'nullable|required_without:address_id|string|max:500',
            'new_city' => 'nullable|required_without:address_id|string|max:100',
            'new_province' => 'nullable|required_without:address_id|string|max:100',
            'shipping_methods' => 'required|array',
            'payment_provider_id' => 'required|exists:providers,id',
            'note' => 'nullable|string',
            'coupon_code' => 'nullable|string',
        ]);

        $customer = auth()->user();
        if ($request->address_id) {
            $address = $customer->addresses()->findOrFail($request->address_id);
        } else {
            $address = \App\Models\CustomerAddress::create([
                'customer_id' => $customer->id,
                'label' => $request->new_label ?? 'Rumah',
                'receiver_name' => $request->new_receiver_name,
                'receiver_phone' => $request->new_receiver_phone,
                'address' => $request->new_address,
                'city' => $request->new_city,
                'province' => $request->new_province,
                'postal_code' => $request->new_postal_code,
                'is_default' => $customer->addresses()->count() === 0,
            ]);
        }
        $cartItems = Cart::where('customer_id', $customer->id)
            ->with(['product.shop', 'variant'])
            ->get();

        if ($cartItems->isEmpty()) {
            return back()->with('error', 'Keranjang kosong.');
        }

        $paymentProvider = Provider::findOrFail($request->payment_provider_id);
        $shippingMethods = $request->shipping_methods;

        DB::beginTransaction();
        try {
            $groupedByShop = $cartItems->groupBy(fn ($i) => $i->product->shop_id);
            $orders = [];
            $grandTotal = 0;

            foreach ($groupedByShop as $shopId => $items) {
                $shop = $items->first()->product->shop;
                $subTotal = $items->sum(fn ($i) => $i->price * $i->quantity);
                $tax = 0;
                $shippingCost = 0;

                if (isset($shippingMethods[$shopId])) {
                    $shippingCost = (float) ($shippingMethods[$shopId]['cost'] ?? 0);
                }

                $couponDiscount = 0;
                if ($request->coupon_code) {
                    $coupon = Coupon::where('code', $request->coupon_code)->first();
                    if ($coupon && $coupon->isValid()) {
                        $couponDiscount = $coupon->calculateDiscount($subTotal);
                        $coupon->increment('usage_count');
                    }
                }

                $total = $subTotal + $tax + $shippingCost - $couponDiscount;

                $order = Order::create([
                    'order_number' => Order::generateOrderNumber(),
                    'customer_id' => $customer->id,
                    'shop_id' => $shop->id,
                    'coupon_code' => $request->coupon_code,
                    'coupon_discount' => $couponDiscount,
                    'sub_total' => $subTotal,
                    'tax' => $tax,
                    'shipping_cost' => $shippingCost,
                    'discount' => 0,
                    'total' => max(0, $total),
                    'shipping_method' => $shippingMethods[$shopId]['service'] ?? null,
                    'shipping_address' => $address->only(['label', 'receiver_name', 'receiver_phone', 'address', 'city', 'province', 'postal_code']),
                    'payment_method' => $paymentProvider->name,
                    'payment_status' => 'unpaid',
                    'order_status' => 'pending',
                    'note' => $request->note,
                ]);

                $order->statusHistory()->create([
                    'status' => 'pending',
                    'changed_by' => $customer->id,
                    'note' => 'Pesanan dibuat',
                ]);

                foreach ($items as $cartItem) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $cartItem->product_id,
                        'product_variant_id' => $cartItem->product_variant_id,
                        'quantity' => $cartItem->quantity,
                        'price' => $cartItem->price,
                        'tax' => $cartItem->tax,
                        'discount' => 0,
                        'sub_total' => $cartItem->price * $cartItem->quantity,
                        'variant_detail' => $cartItem->variant?->variant,
                    ]);
                }

                $orders[] = $order;
                $grandTotal += $total;
            }

            $paymentService = app(PaymentGatewayService::class);
            $paymentResult = $paymentService->createPayment($paymentProvider, [
                'order_id' => $orders[0]->order_number,
                'amount' => $grandTotal,
                'customer' => [
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                ],
                'items' => $cartItems->map(fn ($i) => [
                    'id' => $i->product_id,
                    'name' => $i->product->name,
                    'price' => (int) $i->price,
                    'quantity' => $i->quantity,
                ])->toArray(),
                'success_url' => route('orders.index'),
                'callback_url' => route('webhook.payment', $paymentProvider->id),
            ]);

            if (!$paymentResult['success']) {
                DB::rollBack();
                return back()->with('error', 'Gagal membuat pembayaran: ' . ($paymentResult['message'] ?? 'Unknown error'));
            }

            foreach ($orders as $order) {
                $commission = 0;
                $shop = $order->shop;
                if ($shop->commission_type === 'percentage') {
                    $commission = $order->sub_total * ($shop->commission_value / 100);
                } else {
                    $commission = $shop->commission_value;
                }

                Transaction::create([
                    'transaction_id' => 'TRX-' . $order->order_number,
                    'order_id' => $order->id,
                    'customer_id' => $customer->id,
                    'shop_id' => $order->shop_id,
                    'amount' => $order->total,
                    'admin_commission' => $commission,
                    'vendor_amount' => $order->total - $commission,
                    'payment_method' => $paymentProvider->name,
                    'status' => 'pending',
                ]);
            }

            Cart::where('customer_id', $customer->id)->delete();

            DB::commit();

            if (!empty($paymentResult['redirect_url'])) {
                return redirect()->away($paymentResult['redirect_url']);
            }

            return redirect()->route('orders.index')->with('success', 'Pesanan berhasil dibuat!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function shippingCost(Request $request)
    {
        $request->validate([
            'destination' => 'required',
            'weight' => 'required|integer|min:1',
            'courier' => 'required|string',
            'provider_id' => 'required|exists:providers,id',
        ]);

        $provider = Provider::findOrFail($request->provider_id);
        $shipping = app(ShippingService::class);

        $result = $shipping->getShippingRates($provider, [
            'origin' => $request->origin ?? config('services.shipping.origin_city_id', 501),
            'destination' => $request->destination,
            'weight' => $request->weight,
            'courier' => $request->courier,
        ]);

        return response()->json($result);
    }
}
