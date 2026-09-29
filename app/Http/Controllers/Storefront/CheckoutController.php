<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
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
use App\Services\OrderService;
use App\Services\Payment\PaymentGatewayService;
use App\Services\Payment\PaymentLog;
use App\Services\Shipping\ShippingService;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CheckoutController extends Controller
{
    public function index()
    {
        $cartItems = Cart::where('customer_id', auth()->id())->with(['product.shop', 'variant'])->get()->filter(fn ($item) => $item->product !== null);
        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Keranjang kosong.');
        }

        $shops = $cartItems->groupBy(fn ($item) => $item->product->shop_id)->map(fn ($items) => [
            'shop' => $items->first()->product->shop, 'items' => $items,
            'subtotal' => $items->sum(fn ($item) => Money::of($item->product->getEffectivePrice())->multiply((int) $item->quantity)->toFloat()),
        ]);
        $total = Money::sum(array_map(static fn (array $group) => Money::of($group['subtotal']), $shops->all()))->toFloat();
        $addresses = auth()->user()->addresses;
        $paymentGateways = Provider::ofType('payment')->active()->orderBy('sort_order')->get();
        $shippingProviders = Provider::ofType('shipping')->active()->orderBy('sort_order')->get();

        return view('storefront.checkout.index', compact('shops', 'total', 'addresses', 'paymentGateways', 'shippingProviders'));
    }

    public function process(Request $request, CheckoutCalculator $calculator, PaymentGatewayService $payments)
    {
        $validated = $request->validate([
            'address_id' => 'nullable|exists:customer_addresses,id',
            'new_label' => 'nullable|string|max:100',
            'new_receiver_name' => 'nullable|required_without:address_id|string|max:255',
            'new_receiver_phone' => 'nullable|required_without:address_id|string|max:20',
            'new_address' => 'nullable|required_without:address_id|string|max:500',
            'new_city' => 'nullable|required_without:address_id|string|max:100',
            'new_province' => 'nullable|required_without:address_id|string|max:100',
            'new_postal_code' => 'nullable|string|max:12',
            'new_shipping_destination_id' => 'nullable|required_without:address_id|string|max:100',
            'shipping_methods' => 'present|array',
            'shipping_methods.*.provider_id' => 'nullable|integer|exists:providers,id',
            'shipping_methods.*.courier' => 'nullable|string|max:50',
            'shipping_methods.*.service' => 'nullable|string|max:100',
            'shipping_methods.*.destination' => 'nullable|string|max:100',
            'payment_provider_id' => 'required|integer|exists:providers,id',
            'payment_channel' => 'nullable|array',
            'idempotency_key' => 'nullable|string|max:80',
            'note' => 'nullable|string|max:2000',
            'coupon_code' => 'nullable|string|max:50',
        ]);

        $customer = $request->user();
        $shippingMethods = $request->input('shipping_methods', []);
        if (! is_array($shippingMethods)) {
            $shippingMethods = [];
        }
        $idempotencyKey = $this->idempotencyKey($request, $validated);
        if (isset($validated['coupon_code']) && is_string($validated['coupon_code'])) {
            $validated['coupon_code'] = strtoupper(trim($validated['coupon_code'])) ?: null;
        }

        $provider = Provider::ofType('payment')->active()->find($validated['payment_provider_id']);
        if (! $provider) {
            return back()->withInput()->with('error', 'Metode pembayaran tidak tersedia.');
        }

        if ($idempotencyKey !== null) {
            $replay = Order::where('customer_id', $customer->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($replay) {
                return $this->resumeOrder($replay);
            }
        }

        try {
            $created = DB::transaction(function () use ($validated, $shippingMethods, $customer, $provider, $calculator, $idempotencyKey) {
                $address = $this->resolveAddress($validated, (int) $customer->id);
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
                    'discount' => $quote['discount'], 'grand_total' => $quote['grand_total'], 'status' => PaymentStatus::Pending->value,
                    'expired_at' => now()->addMinutes($this->expiryMinutes()),
                ]);

                $orders = collect();
                $first = true;

                foreach ($quote['shops'] as $shopQuote) {
                    $selection = $shippingMethods[$shopQuote->shop->id] ?? [];
                    $total = Money::of($shopQuote->subtotal)
                        ->add($shopQuote->tax)
                        ->add($shopQuote->shipping)
                        ->subtract($shopQuote->couponDiscount)
                        ->maxZero();

                    $order = Order::create([
                        'payment_group_id' => $group->id, 'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id,
                        'shop_id' => $shopQuote->shop->id, 'coupon_code' => $quote['coupon']?->code,
                        'coupon_discount' => $shopQuote->couponDiscount, 'sub_total' => $shopQuote->subtotal, 'tax' => $shopQuote->tax,
                        'shipping_cost' => $shopQuote->shipping, 'discount' => 0, 'total' => $total->toDecimal(),
                        'idempotency_key' => $first ? $idempotencyKey : null,
                        'shipping_method' => $selection['courier'] ?? null, 'shipping_service' => $selection['service'] ?? null,
                        'shipping_address' => $address->only(['label', 'receiver_name', 'receiver_phone', 'address', 'city', 'province', 'postal_code']),
                        'payment_method' => $provider->api_format, 'payment_status' => PaymentStatus::Unpaid->value,
                        'order_status' => OrderStatus::Pending->stored(), 'note' => $validated['note'] ?? null,
                    ]);

                    $first = false;
                    $group->orders()->attach($order->id, ['amount' => $total->toDecimal()]);
                    $order->statusHistory()->create(['status' => OrderStatus::Pending->stored(), 'changed_by' => $customer->id, 'note' => 'Pesanan dibuat.']);

                    foreach ($shopQuote->shopLines as $line) {
                        OrderItem::create([
                            'order_id' => $order->id, 'product_id' => $line->product->id, 'product_variant_id' => $line->variant?->id,
                            'quantity' => $line->quantity, 'price' => $line->price->toDecimal(),
                            'tax' => $line->line_total->multiply($line->tax_rate / 100)->toDecimal(),
                            'discount' => 0, 'sub_total' => $line->line_total->toDecimal(), 'variant_detail' => $line->variant?->variant,
                        ]);

                        if ($line->variant) {
                            $line->variant->decrement('stock', (int) $line->quantity);
                        } else {
                            $line->product->decrement('current_stock', (int) $line->quantity);
                        }
                    }

                    Transaction::create([
                        'transaction_id' => 'TRX-'.$order->order_number, 'payment_group_id' => $group->id, 'order_id' => $order->id,
                        'customer_id' => $customer->id, 'shop_id' => $order->shop_id, 'amount' => $order->total, 'admin_commission' => 0,
                        'vendor_amount' => 0, 'payment_method' => $this->transactionPaymentMethod($provider), 'status' => 'pending',
                    ]);

                    $orders->push($order);
                }

                if ($quote['coupon']) {
                    $quote['coupon']->increment('usage_count');
                    CouponUsage::create([
                        'coupon_id' => $quote['coupon']->id, 'customer_id' => $customer->id,
                        'order_id' => $orders->first()->id, 'discount_amount' => $quote['discount'],
                    ]);
                }

                return ['group' => $group, 'orders' => $orders];
            }, 3);
        } catch (QueryException $e) {
            PaymentLog::channel('warning', 'Checkout lost an idempotency race', [
                'customer_id' => $customer->id,
                'exception' => $e::class,
            ]);

            $replay = $idempotencyKey === null
                ? null
                : Order::where('customer_id', $customer->id)->where('idempotency_key', $idempotencyKey)->first();

            if ($replay) {
                return $this->resumeOrder($replay);
            }

            return back()->withInput()->with('error', 'Checkout tidak dapat diproses. Silakan coba kembali.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            PaymentLog::channel('error', 'Checkout failed', [
                'customer_id' => $customer->id,
                'exception' => $e::class,
            ]);

            return back()->withInput()->with('error', 'Checkout tidak dapat diproses. Silakan coba kembali.');
        }

        $group = $created['group'];
        // $created['orders'] is a base Support collection; re-query as
        // Eloquent so items.product is eager-loaded without N+1.
        $orders = Order::query()
            ->whereIn('id', collect($created['orders'])->map(fn ($o) => $o->getKey())->all())
            ->with('items.product')
            ->get();

        $payment = $payments->createPayment($provider, [
            'order_id' => $group->payment_number, 'amount' => $group->grand_total,
            'channel' => is_array($validated['payment_channel'] ?? null) ? ($validated['payment_channel'][$provider->id] ?? 'default') : 'default',
            'customer' => ['name' => $customer->name, 'email' => $customer->email, 'phone' => $customer->phone],
            'items' => $this->paymentItems($orders), 'success_url' => route('orders.index'),
            'callback_url' => route('webhook.payment', $provider),
        ]);

        if (! ($payment['success'] ?? false)) {
            $this->abortPayment($group);

            return back()->withInput()->with('error', 'Gateway pembayaran tidak dapat membuat transaksi. Silakan pilih metode lain atau coba kembali.');
        }

        $group->forceFill([
            'gateway_reference' => $payment['transaction_id'] ?? $payment['invoice_id'] ?? $payment['reference'] ?? $group->gateway_reference,
            'gateway_response' => is_array($payment['raw'] ?? null) ? $payment['raw'] : null,
        ])->save();

        Cart::where('customer_id', $customer->id)->delete();

        return ! empty($payment['redirect_url'])
            ? redirect()->away($payment['redirect_url'])
            : redirect()->route('orders.index')->with('success', 'Pesanan dibuat. Selesaikan pembayaran Anda.');
    }

    public function shippingCost(Request $request, ShippingService $shipping)
    {
        $data = $request->validate([
            'shop_id' => 'required|integer|exists:shops,id', 'destination' => 'required|string|max:100',
            'courier' => 'required|string|max:50', 'provider_id' => 'required|integer',
        ]);

        $provider = Provider::ofType('shipping')->active()->find($data['provider_id']);
        if (! $provider) {
            return response()->json(['success' => false, 'message' => 'Provider pengiriman tidak tersedia.'], 422);
        }

        $cart = Cart::where('customer_id', $request->user()->id)->whereHas('product', fn ($q) => $q->where('shop_id', $data['shop_id']))->with('product')->get();
        $origin = SystemSetting::get("shop_shipping_origin_{$data['shop_id']}") ?: SystemSetting::get('shipping_origin');
        if (! $origin || $cart->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Data pengiriman belum lengkap.'], 422);
        }

        return response()->json($shipping->getShippingRates($provider, [
            'origin' => $origin, 'destination' => $data['destination'], 'courier' => $data['courier'],
            'weight' => $cart->sum(fn ($item) => max(1, (int) $item->product->weight) * $item->quantity),
        ]));
    }

    private function abortPayment(PaymentGroup $group): void
    {
        DB::transaction(function () use ($group): void {
            $locked = PaymentGroup::whereKey($group->getKey())->lockForUpdate()->first();

            if (! $locked) {
                return;
            }

            $locked->forceFill([
                'status' => PaymentStatus::Failed->value,
                'expired_at' => now(),
            ])->save();

            foreach ($locked->orders()->lockForUpdate()->get() as $order) {
                $order->forceFill([
                    'order_status' => OrderStatus::Failed->stored(),
                    'payment_status' => PaymentStatus::Unpaid->value,
                    'cancel_reason' => 'Gateway pembayaran tidak dapat membuat transaksi.',
                ])->save();

                $order->statusHistory()->create([
                    'status' => OrderStatus::Failed->stored(),
                    'note' => 'Checkout gagal karena gateway menolak permintaan pembayaran.',
                ]);

                app(OrderService::class)->restoreStock($order);
            }
        }, 3);
    }

    private function resumeOrder(Order $order)
    {
        $response = $order->paymentGroup?->gateway_response;
        $response = is_array($response) ? $response : [];

        foreach (['redirect_url', 'invoice_url', 'payment_url', 'checkout_url', 'redirect'] as $key) {
            $candidate = $response[$key] ?? null;

            if (is_string($candidate) && $candidate !== '') {
                return redirect()->away($candidate);
            }
        }

        return redirect()->route('orders.show', $order)->with('info', 'Pesanan ini sudah pernah dibuat.');
    }

    private function idempotencyKey(Request $request, array $validated): ?string
    {
        $candidate = $validated['idempotency_key'] ?? $request->header('Idempotency-Key');
        $candidate = is_string($candidate) ? trim($candidate) : '';

        if ($candidate === '' || mb_strlen($candidate) > 80) {
            return null;
        }

        return $candidate;
    }

    private function expiryMinutes(): int
    {
        return max(15, (int) (SystemSetting::get('payment_expiry_minutes', '1440') ?: 1440));
    }

    private function resolveAddress(array $data, int $customerId): CustomerAddress
    {
        if (! empty($data['address_id'])) {
            return CustomerAddress::where('customer_id', $customerId)->findOrFail($data['address_id']);
        }

        return CustomerAddress::create([
            'customer_id' => $customerId, 'label' => $data['new_label'] ?? 'Rumah', 'receiver_name' => $data['new_receiver_name'],
            'receiver_phone' => $data['new_receiver_phone'], 'address' => $data['new_address'], 'city' => $data['new_city'],
            'province' => $data['new_province'], 'postal_code' => $data['new_postal_code'] ?? null,
            'shipping_destination_id' => $data['new_shipping_destination_id'],
            'is_default' => ! CustomerAddress::where('customer_id', $customerId)->exists(),
        ]);
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
                $productName = $item->product?->name ?? ('Produk #'.$item->product_id);
                $items[] = [
                    'id' => 'ITEM-'.$item->id,
                    'name' => mb_substr($productName, 0, 50),
                    'price' => (int) Money::of($item->price)->toDecimal(),
                    'quantity' => (int) $item->quantity,
                ];
            }

            $shipping = Money::of($order->shipping_cost);
            if ($shipping->isPositive()) {
                $items[] = ['id' => 'SHIP-'.$order->id, 'name' => 'Biaya pengiriman', 'price' => (int) $shipping->toDecimal(), 'quantity' => 1];
            }

            $tax = Money::of($order->tax);
            if ($tax->isPositive()) {
                $items[] = ['id' => 'TAX-'.$order->id, 'name' => 'Pajak', 'price' => (int) $tax->toDecimal(), 'quantity' => 1];
            }

            $discount = Money::of($order->coupon_discount);
            if ($discount->isPositive()) {
                $items[] = ['id' => 'DISC-'.$order->id, 'name' => 'Diskon', 'price' => -(int) $discount->toDecimal(), 'quantity' => 1];
            }
        }

        return $items;
    }
}
