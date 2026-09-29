<?php

declare(strict_types=1);

namespace App\Services\Vendor;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * Vendor-side order line editing.
 *
 * The controller used to wipe every line, rebuild them with the catalogue
 * price, hard-code tax to zero and add the shipping cost back in. That silently
 * destroyed refund history and produced totals that disagreed with the payment
 * group. This service is the single place that recomputes an order, and it
 * keeps tax, discount and every already-refunded line intact.
 */
final class OrderEditService
{
    /** @return list<string> */
    public static function editableStatuses(): array
    {
        return [
            'pending',
            'payment_pending',
            'paid',
            'confirmed',
            'processing',
            'cancel_requested',
        ];
    }

    public function __construct(private readonly VendorScope $scope) {}

    /**
     * @param  array<int, array{product_id?: int|string, product_variant_id?: int|string|null, quantity: int|string}>  $lines
     * @param  array<string, mixed>  $header
     */
    public function apply(Order $order, array $lines, array $header = []): Order
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($order, $lines, $header): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            $this->assertOwned($locked);
            $this->assertEditable($locked);

            $items = OrderItem::query()->where('order_id', $locked->getKey())->lockForUpdate()->get();
            $refundedItemIds = $items
                ->filter(fn (OrderItem $item): bool => (float) $item->refund_amount > 0.0)
                ->map(fn (OrderItem $item): int => (int) $item->getKey())
                ->all();

            $resolved = $this->resolveLines($lines, $refundedItemIds);

            $this->applyHeader($locked, $header);

            OrderItem::query()->where('order_id', $locked->getKey())->delete();

            $subTotal = Money::zero();
            $taxTotal = Money::zero();
            $discountTotal = Money::zero();

            foreach ($resolved as $line) {
                OrderItem::query()->create([
                    'order_id' => $locked->getKey(),
                    'product_id' => $line['product_id'],
                    'product_variant_id' => $line['product_variant_id'],
                    'quantity' => $line['quantity'],
                    'price' => $line['price']->toDecimal(),
                    'tax' => $line['tax']->toDecimal(),
                    'discount' => $line['discount']->toDecimal(),
                    'sub_total' => $line['sub_total']->toDecimal(),
                ]);

                $subTotal = $subTotal->add($line['sub_total']);
                $taxTotal = $taxTotal->add($line['tax']);
                $discountTotal = $discountTotal->add($line['discount']);
            }

            $shipping = Money::of($locked->shipping_cost)->maxZero();
            $orderDiscount = Money::of($locked->discount)->maxZero();
            $couponDiscount = Money::of($locked->coupon_discount)->maxZero();

            $total = $subTotal
                ->add($taxTotal)
                ->add($shipping)
                ->subtract($discountTotal)
                ->subtract($orderDiscount)
                ->subtract($couponDiscount)
                ->maxZero();

            $locked->forceFill([
                'sub_total' => $subTotal->toDecimal(),
                'tax' => $taxTotal->toDecimal(),
                'total' => $total->toDecimal(),
            ])->save();

            $locked->statusHistory()->create([
                'status' => (string) $locked->order_status,
                'changed_by' => $this->scope->userId(),
                'note' => 'Pesanan diedit oleh penjual. Subtotal '.Money::of($subTotal->toFloat())->toDecimal().'.',
            ]);

            app(AuditLogger::class)->log('vendor.order.edited', $locked, [], [
                'sub_total' => $subTotal->toDecimal(),
                'tax' => $taxTotal->toDecimal(),
                'total' => $total->toDecimal(),
                'items' => count($resolved),
            ], $this->scope->userId());

            return $locked->fresh(['items', 'customer', 'paymentGroup']);
        }, 3);
    }

    /** Recompute without persisting: used to render the edit screen totals. */
    public function preview(array $lines): array
    {
        $resolved = $this->resolveLines($lines, []);

        $subTotal = Money::zero();
        $taxTotal = Money::zero();
        $discountTotal = Money::zero();

        foreach ($resolved as $line) {
            $subTotal = $subTotal->add($line['sub_total']);
            $taxTotal = $taxTotal->add($line['tax']);
            $discountTotal = $discountTotal->add($line['discount']);
        }

        return [
            'lines' => $resolved,
            'sub_total' => $subTotal,
            'tax' => $taxTotal,
            'discount' => $discountTotal,
            'total' => $subTotal->add($taxTotal)->subtract($discountTotal)->maxZero(),
        ];
    }

    private function applyHeader(Order $order, array $header): void
    {
        $attributes = [];

        if (array_key_exists('note', $header)) {
            $attributes['note'] = VendorScope::cleanNullable($header['note'], 1000);
        }

        if (array_key_exists('shipping_cost', $header) && $header['shipping_cost'] !== null && $header['shipping_cost'] !== '') {
            $attributes['shipping_cost'] = Money::of($header['shipping_cost'])->maxZero()->toDecimal();
        }

        if (array_key_exists('discount', $header) && $header['discount'] !== null && $header['discount'] !== '') {
            $attributes['discount'] = Money::of($header['discount'])->maxZero()->toDecimal();
        }

        if (array_key_exists('shipping_tracking_id', $header)) {
            $attributes['shipping_tracking_id'] = VendorScope::cleanNullable($header['shipping_tracking_id'], 80);
        }

        if ($attributes !== []) {
            $order->forceFill($attributes)->save();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  list<int>  $lockedItemIds
     * @return list<array{product_id: int|null, product_variant_id: int|null, quantity: int, price: Money, tax: Money, discount: Money, sub_total: Money}>
     */
    private function resolveLines(array $lines, array $lockedItemIds): array
    {
        $shopId = $this->scope->shopId();
        $resolved = [];
        $seen = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $productId = isset($line['product_id']) ? (int) $line['product_id'] : 0;
            $variantId = isset($line['product_variant_id']) && $line['product_variant_id'] !== null
                ? (int) $line['product_variant_id']
                : null;
            $quantity = max(0, (int) ($line['quantity'] ?? 0));

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $key = $productId.'-'.($variantId ?? 0);

            if (isset($seen[$key])) {
                $seen[$key]['quantity'] += $quantity;

                continue;
            }

            $product = Product::query()
                ->where('shop_id', $shopId)
                ->whereKey($productId)
                ->first();

            if ($product === null) {
                throw ValidationException::withMessages([
                    'items' => 'Produk tidak ditemukan pada toko Anda.',
                ]);
            }

            $variant = null;

            if ($variantId !== null) {
                $variant = ProductVariant::query()
                    ->where('product_id', $product->getKey())
                    ->whereKey($variantId)
                    ->first();

                if ($variant === null) {
                    throw ValidationException::withMessages([
                        'items' => 'Varian produk tidak valid.',
                    ]);
                }
            }

            $price = $this->priceFor($product, $variant);
            $subTotal = $price->multiply($quantity);
            $discount = Money::of($line['discount'] ?? 0)->maxZero()->min($subTotal);
            $tax = $this->taxFor($subTotal->subtract($discount), $product);

            $seen[$key] = [
                'product_id' => (int) $product->getKey(),
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
                'price' => $price,
                'tax' => $tax,
                'discount' => $discount,
                'sub_total' => $subTotal,
            ];
        }

        if ($seen === []) {
            throw ValidationException::withMessages([
                'items' => 'Pesanan harus memiliki minimal satu baris produk.',
            ]);
        }

        return array_values($seen);
    }

    private function priceFor(Product $product, ?ProductVariant $variant): Money
    {
        if ($variant !== null && (float) $variant->effective_price > 0.0) {
            return Money::of($variant->effective_price)->maxZero();
        }

        return Money::of($product->effectivePrice())->maxZero();
    }

    private function taxFor(Money $net, Product $product): Money
    {
        $rate = Money::of($product->tax)->maxZero();

        if ($rate->isZero() || ! $net->isPositive()) {
            return Money::zero();
        }

        if ($product->tax_type === 'inclusive') {
            return Money::of($net->multiply(1 + $rate->toFloat())->subtract($net->toFloat())->toFloat())->maxZero();
        }

        return $net->multiply($rate->toFloat())->maxZero();
    }

    private function assertOwned(Order $order): void
    {
        abort_if((int) $order->shop_id !== $this->scope->shopId(), 403);
    }

    private function assertEditable(Order $order): void
    {
        if (in_array((string) $order->order_status, self::editableStatuses(), true)) {
            return;
        }

        throw ValidationException::withMessages([
            'order' => 'Pesanan pada status '.str_replace('_', ' ', (string) $order->order_status).' tidak dapat diedit.',
        ]);
    }
}
