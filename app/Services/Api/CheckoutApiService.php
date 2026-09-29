<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CouponUsage;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentGroup;
use App\Models\Provider;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CheckoutCalculator;
use App\Services\OrderService;
use App\Services\Payment\PaymentGatewayService;
use App\Support\ApiResponse;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Checkout for API callers.
 *
 * Prices are never read from the request: the quote is recalculated from locked
 * product rows by CheckoutCalculator, exactly as the storefront flow does.
 */
final class CheckoutApiService
{
    public function __construct(
        private readonly CheckoutCalculator $calculator,
        private readonly PaymentGatewayService $payments
    ) {}

    public function preview(User $customer, array $input): array
    {
        return DB::transaction(function () use ($customer, $input): array {
            $address = $this->resolveAddress($customer, $input);
            $cartItems = Cart::where('customer_id', $customer->id)
                ->with(['product.shop', 'variant'])
                ->lockForUpdate()
                ->get();

            $this->assertCartNotEmpty($cartItems);

            $quote = $this->calculator->calculate(
                $customer,
                $cartItems,
                $input['shipping_methods'] ?? [],
                $input['coupon_code'] ?? null,
                $address->toArray()
            );

            return $this->present($quote, $address, []);
        }, 3);
    }

    public function place(User $customer, array $input): array
    {
        $provider = Provider::ofType('payment')->active()->find($input['payment_provider_id'] ?? null);

        if ($provider === null) {
            throw ValidationException::withMessages(['payment_provider_id' => 'Metode pembayaran tidak tersedia.']);
        }

        $idempotencyKey = $this->idempotencyKey($input);

        if ($idempotencyKey !== null) {
            $replay = Order::where('customer_id', $customer->id)
                ->where('idempotency_key', $idempotencyKey)
                ->with(['items.product', 'shop'])
                ->first();

            if ($replay !== null) {
                return $this->presentCheckout($replay, $replay->paymentGroup, true);
            }
        }

        try {
            $created = DB::transaction(function () use ($customer, $input, $provider, $idempotencyKey): array {
                $address = $this->resolveAddress($customer, $input);
                $cartItems = Cart::where('customer_id', $customer->id)
                    ->with(['product.shop', 'variant'])
                    ->lockForUpdate()
                    ->get();

                $this->assertCartNotEmpty($cartItems);

                foreach ($input['shipping_methods'] ?? [] as $shopId => $selection) {
                    if (! empty($selection['destination'])
                        && (! $address->shipping_destination_id || $selection['destination'] !== $address->shipping_destination_id)) {
                        throw ValidationException::withMessages([
                            "shipping_methods.{$shopId}.destination" => 'Tujuan pengiriman harus sesuai alamat yang dipilih.',
                        ]);
                    }
                }

                $quote = $this->calculator->calculate(
                    $customer,
                    $cartItems,
                    $input['shipping_methods'] ?? [],
                    $input['coupon_code'] ?? null,
                    $address->toArray()
                );

                $group = PaymentGroup::create([
                    'payment_number' => PaymentGroup::generateNumber(),
                    'customer_id' => $customer->id,
                    'provider_id' => $provider->id,
                    'subtotal' => $quote['subtotal'],
                    'tax' => $quote['tax'],
                    'shipping_cost' => $quote['shipping'],
                    'discount' => $quote['discount'],
                    'grand_total' => $quote['grand_total'],
                    'status' => PaymentStatus::Pending->value,
                    'expired_at' => now()->addMinutes($this->expiryMinutes()),
                ]);

                $orders = collect();
                $first = true;

                foreach ($quote['shops'] as $shopQuote) {
                    $selection = $input['shipping_methods'][$shopQuote->shop->id] ?? [];
                    $total = Money::of($shopQuote->subtotal)
                        ->add($shopQuote->tax)
                        ->add($shopQuote->shipping)
                        ->subtract($shopQuote->couponDiscount)
                        ->maxZero();

                    $order = Order::create([
                        'payment_group_id' => $group->id,
                        'order_number' => Order::generateOrderNumber(),
                        'customer_id' => $customer->id,
                        'shop_id' => $shopQuote->shop->id,
                        'coupon_code' => $quote['coupon']?->code,
                        'coupon_discount' => $shopQuote->couponDiscount,
                        'sub_total' => $shopQuote->subtotal,
                        'tax' => $shopQuote->tax,
                        'shipping_cost' => $shopQuote->shipping,
                        'discount' => 0,
                        'total' => $total->toDecimal(),
                        'idempotency_key' => $first ? $idempotencyKey : null,
                        'shipping_method' => $selection['courier'] ?? null,
                        'shipping_service' => $selection['service'] ?? null,
                        'shipping_address' => $address->only([
                            'label', 'receiver_name', 'receiver_phone', 'address', 'city', 'province', 'postal_code',
                        ]),
                        'payment_method' => $provider->api_format,
                        'payment_status' => PaymentStatus::Unpaid->value,
                        'order_status' => OrderStatus::Pending->stored(),
                        'note' => $input['note'] ?? null,
                    ]);

                    $first = false;
                    $group->orders()->attach($order->id, ['amount' => $total->toDecimal()]);
                    $order->statusHistory()->create([
                        'status' => OrderStatus::Pending->stored(),
                        'changed_by' => $customer->id,
                        'note' => 'Pesanan dibuat melalui API.',
                    ]);

                    foreach ($shopQuote->shopLines as $line) {
                        OrderItem::create([
                            'order_id' => $order->id,
                            'product_id' => $line->product->id,
                            'product_variant_id' => $line->variant?->id,
                            'quantity' => $line->quantity,
                            'price' => $line->price->toDecimal(),
                            'tax' => $line->line_total->multiply($line->tax_rate / 100)->toDecimal(),
                            'discount' => 0,
                            'sub_total' => $line->line_total->toDecimal(),
                            'variant_detail' => $line->variant?->variant,
                        ]);

                        $line->variant === null
                            ? $line->product->decrement('current_stock', (int) $line->quantity)
                            : $line->variant->decrement('stock', (int) $line->quantity);
                    }

                    Transaction::create([
                        'transaction_id' => 'TRX-'.$order->order_number,
                        'payment_group_id' => $group->id,
                        'order_id' => $order->id,
                        'customer_id' => $customer->id,
                        'shop_id' => $order->shop_id,
                        'amount' => $order->total,
                        'admin_commission' => 0,
                        'vendor_amount' => 0,
                        'payment_method' => $this->transactionPaymentMethod($provider),
                        'status' => 'pending',
                    ]);

                    $orders->push($order);
                }

                if ($quote['coupon'] !== null) {
                    $quote['coupon']->increment('usage_count');
                    CouponUsage::create([
                        'coupon_id' => $quote['coupon']->id,
                        'customer_id' => $customer->id,
                        'order_id' => $orders->first()->id,
                        'discount_amount' => $quote['discount'],
                    ]);
                }

                return ['group' => $group, 'orders' => $orders];
            }, 3);
        } catch (QueryException $e) {
            $replay = $idempotencyKey === null
                ? null
                : Order::where('customer_id', $customer->id)->where('idempotency_key', $idempotencyKey)->first();

            if ($replay !== null) {
                return $this->presentCheckout($replay, $replay->paymentGroup, true);
            }

            throw $e;
        }

        $group = $created['group'];
        $orders = $created['orders'];

        $payment = $this->payments->createPayment($provider, [
            'order_id' => $group->payment_number,
            'amount' => $group->grand_total,
            'channel' => $input['payment_channel'][$provider->id] ?? 'default',
            'customer' => [
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ],
            'items' => $this->paymentItems($orders),
            'success_url' => url('/orders'),
            'callback_url' => url('/webhook/payment/'.$provider->id),
        ]);

        if (! ($payment['success'] ?? false)) {
            $this->abortPayment($group);

            throw ValidationException::withMessages([
                'payment_provider_id' => 'Gateway pembayaran tidak dapat membuat transaksi.',
            ]);
        }

        $group->forceFill([
            'gateway_reference' => $payment['transaction_id'] ?? $payment['invoice_id'] ?? $payment['reference'] ?? $group->gateway_reference,
            'gateway_response' => is_array($payment['raw'] ?? null) ? $payment['raw'] : null,
        ])->save();

        Cart::where('customer_id', $customer->id)->delete();

        return $this->presentCheckout($orders->first(), $group, false, [
            'payment' => [
                'success' => true,
                'reference' => $payment['transaction_id'] ?? $payment['invoice_id'] ?? $payment['reference'] ?? null,
                'redirect_url' => $payment['redirect_url'] ?? null,
                'invoice_url' => $payment['invoice_url'] ?? null,
            ],
        ]);
    }

    public function presentCheckout(Order $order, ?PaymentGroup $group, bool $replayed, array $extra = []): array
    {
        $order->loadMissing(['items.product', 'shop', 'statusHistory']);

        return array_merge([
            'order' => [
                'id' => (int) $order->id,
                'order_number' => $order->order_number,
                'status' => $order->order_status,
                'payment_status' => $order->payment_status,
                'total' => ApiResponse::money($order->total),
                'currency' => $order->currency ?? 'IDR',
            ],
            'payment_group' => $group === null ? null : [
                'id' => (int) $group->id,
                'payment_number' => $group->payment_number,
                'status' => $group->status,
                'grand_total' => ApiResponse::money($group->grand_total),
                'expires_at' => ApiResponse::iso($group->expired_at),
            ],
            'replayed' => $replayed,
        ], $extra);
    }

    private function present(array $quote, CustomerAddress $address, array $orders): array
    {
        $shops = [];

        foreach ($quote['shops'] as $shopQuote) {
            $shops[] = [
                'shop' => [
                    'id' => (int) $shopQuote->shop->id,
                    'name' => $shopQuote->shop->name,
                    'slug' => $shopQuote->shop->slug,
                ],
                'subtotal' => ApiResponse::money($shopQuote->subtotal),
                'tax' => ApiResponse::money($shopQuote->tax),
                'shipping' => ApiResponse::money($shopQuote->shipping),
                'coupon_discount' => ApiResponse::money($shopQuote->couponDiscount),
                'total' => ApiResponse::money(
                    Money::of($shopQuote->subtotal)
                        ->add($shopQuote->tax)
                        ->add($shopQuote->shipping)
                        ->subtract($shopQuote->couponDiscount)
                        ->maxZero()
                        ->toDecimal()
                ),
            ];
        }

        return [
            'shops' => $shops,
            'subtotal' => ApiResponse::money($quote['subtotal']),
            'tax' => ApiResponse::money($quote['tax']),
            'shipping' => ApiResponse::money($quote['shipping']),
            'discount' => ApiResponse::money($quote['discount']),
            'grand_total' => ApiResponse::money($quote['grand_total']),
            'coupon' => $quote['coupon'] === null ? null : [
                'id' => (int) $quote['coupon']->id,
                'code' => $quote['coupon']->code,
                'coupon_type' => $quote['coupon']->coupon_type,
                'discount_value' => ApiResponse::money($quote['coupon']->discount_value),
            ],
            'address' => [
                'id' => (int) $address->id,
                'label' => $address->label,
                'receiver_name' => $address->receiver_name,
                'receiver_phone' => $address->receiver_phone,
                'address' => $address->address,
                'city' => $address->city,
                'province' => $address->province,
                'postal_code' => $address->postal_code,
                'shipping_destination_id' => $address->shipping_destination_id,
            ],
            'orders' => $orders,
            'currency' => 'IDR',
        ];
    }

    private function assertCartNotEmpty($cartItems): void
    {
        if ($cartItems->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Keranjang kosong.']);
        }
    }

    private function resolveAddress(User $customer, array $input): CustomerAddress
    {
        if (! empty($input['address_id'])) {
            $address = CustomerAddress::where('customer_id', $customer->id)
                ->find($input['address_id']);

            if ($address === null) {
                throw ValidationException::withMessages(['address_id' => 'Alamat tidak ditemukan.']);
            }

            return $address;
        }

        return CustomerAddress::create([
            'customer_id' => $customer->id,
            'label' => $input['new_label'] ?? 'Rumah',
            'receiver_name' => $input['new_receiver_name'],
            'receiver_phone' => $input['new_receiver_phone'],
            'address' => $input['new_address'],
            'city' => $input['new_city'],
            'province' => $input['new_province'],
            'postal_code' => $input['new_postal_code'] ?? null,
            'shipping_destination_id' => $input['new_shipping_destination_id'],
            'is_default' => ! CustomerAddress::where('customer_id', $customer->id)->exists(),
        ]);
    }

    private function abortPayment(PaymentGroup $group): void
    {
        DB::transaction(function () use ($group): void {
            $locked = PaymentGroup::whereKey($group->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                return;
            }

            $locked->forceFill(['status' => PaymentStatus::Failed->value, 'expired_at' => now()])->save();

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

    private function idempotencyKey(array $input): ?string
    {
        $candidate = trim((string) ($input['idempotency_key'] ?? ''));

        return $candidate === '' || mb_strlen($candidate) > 80 ? null : $candidate;
    }

    private function expiryMinutes(): int
    {
        return max(15, (int) (SystemSetting::get('payment_expiry_minutes', '1440') ?: 1440));
    }

    private function transactionPaymentMethod(Provider $provider): string
    {
        return match (true) {
            str_contains((string) $provider->api_format, 'midtrans') => 'midtrans',
            str_contains((string) $provider->api_format, 'xendit') => 'xendit',
            default => 'transfer',
        };
    }

    private function paymentItems($orders): array
    {
        $items = [];

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $items[] = [
                    'id' => 'ITEM-'.$item->id,
                    'name' => mb_substr((string) $item->product->name, 0, 50),
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
