<?php

declare(strict_types=1);

namespace App\Services\Vendor;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Shop;
use App\Services\OrderWorkflowService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Point-of-sale sales.
 *
 * Stock is the scarce resource at a till, so every deduction locks the product
 * row, refuses to go negative, writes a stock movement and re-reads the balance
 * before the order is committed. A hold does not touch stock at all; the resume
 * path runs the same locked deduction.
 */
final class PosService
{
    public function __construct(private readonly VendorScope $scope) {}

    /** @param  array<int, array<string, mixed>>  $lines */
    public function sell(array $payload): Order
    {
        return DB::transaction(function () use ($payload): Order {
            $shop = Shop::query()->lockForUpdate()->findOrFail($this->scope->shopId());
            $hold = (bool) ($payload['hold'] ?? false);

            $lines = $this->resolveLines($payload['items'] ?? []);

            $subTotal = Money::zero();
            $taxTotal = Money::zero();

            foreach ($lines as $line) {
                $subTotal = $subTotal->add($line['sub_total']);
                $taxTotal = $taxTotal->add($line['tax']);
            }

            $discount = Money::of($payload['discount'] ?? 0)->maxZero()->min($subTotal);
            $total = $subTotal->add($taxTotal)->subtract($discount)->maxZero();

            $order = Order::query()->create([
                'order_number' => $this->orderNumber($hold),
                'customer_id' => $this->scope->userId(),
                'shop_id' => $shop->getKey(),
                'sub_total' => $subTotal->toDecimal(),
                'tax' => $taxTotal->toDecimal(),
                'discount' => $discount->toDecimal(),
                'total' => $total->toDecimal(),
                'shipping_cost' => Money::zero()->toDecimal(),
                'payment_method' => VendorScope::clean($payload['payment_method'] ?? 'cash', 40),
                'payment_status' => $hold ? 'unpaid' : 'paid',
                'order_status' => $hold ? OrderStatus::Pending->stored() : OrderStatus::Delivered->stored(),
                'fulfillment_status' => $hold ? 'unfulfilled' : 'fulfilled',
                'source' => 'pos',
                'note' => 'POS: '.VendorScope::clean($payload['customer_name'] ?? 'Pelanggan walk-in', 200),
            ]);

            foreach ($lines as $line) {
                OrderItem::query()->create([
                    'order_id' => $order->getKey(),
                    'product_id' => $line['product_id'],
                    'product_variant_id' => $line['product_variant_id'],
                    'quantity' => $line['quantity'],
                    'price' => $line['price']->toDecimal(),
                    'tax' => $line['tax']->toDecimal(),
                    'discount' => $line['discount']->toDecimal(),
                    'sub_total' => $line['sub_total']->toDecimal(),
                ]);

                if ($hold) {
                    continue;
                }

                $this->deduct(
                    Product::query()->where('shop_id', $shop->getKey())->lockForUpdate()->findOrFail($line['product_id']),
                    $line['quantity'],
                    $order->getKey(),
                );
            }

            OrderStatusHistory::query()->create([
                'order_id' => $order->getKey(),
                'status' => (string) $order->order_status,
                'changed_by' => $this->scope->userId(),
                'note' => $hold ? 'POS hold order' : 'POS sale',
            ]);

            return $order->refresh(['items']);
        }, 3);
    }

    public function resume(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            abort_if((int) $locked->shop_id !== $this->scope->shopId(), 403);
            abort_if(! Str::startsWith((string) $locked->order_number, 'HOLD-'), 400, 'Pesanan ini bukan POS hold.');
            abort_if((string) $locked->order_status !== OrderStatus::Pending->stored(), 400, 'Pesanan hold sudah diproses.');

            $items = OrderItem::query()
                ->where('order_id', $locked->getKey())
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['order' => 'Pesanan hold tidak memiliki item.']);
            }

            foreach ($items as $item) {
                $product = Product::query()
                    ->where('shop_id', $this->scope->shopId())
                    ->lockForUpdate()
                    ->find($item->product_id);

                if ($product === null) {
                    continue;
                }

                $this->deduct($product, (int) $item->quantity, $locked->getKey());
            }

            $locked->extend([
                'payment_status' => 'paid',
                'fulfillment_status' => 'fulfilled',
            ])->save();

            OrderStatusHistory::query()->create([
                'order_id' => $locked->getKey(),
                'status' => OrderStatus::Delivered->stored(),
                'changed_by' => $this->scope->userId(),
                'note' => 'POS hold dilanjutkan',
            ]);

            return $locked->fresh(['items']);
        }, 3);
    }

    public function cancelHold(Order $order): Order
    {
        abort_if((int) $order->shop_id !== $this->scope->shopId(), 403);
        abort_if(! Str::startsWith((string) $order->order_number, 'HOLD-'), 400, 'Pesanan ini bukan POS hold.');

        return app(OrderWorkflowService::class)->cancel($order, $this->scope->userId(), 'POS hold dibatalkan');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return list<array{product_id: int, product_variant_id: int|null, quantity: int, price: Money, tax: Money, discount: Money, sub_total: Money}>
     */
    private function resolveLines(array $items): array
    {
        $shopId = $this->scope->shopId();
        $merged = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $productId = (int) ($item['product_id'] ?? 0);
            $variantId = isset($item['product_variant_id']) && $item['product_variant_id'] !== null
                ? (int) $item['product_variant_id']
                : null;
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $key = $productId.'-'.($variantId ?? 0);
            $merged[$key] = ($merged[$key] ?? 0) + $quantity;
        }

        if ($merged === []) {
            throw ValidationException::withMessages(['items' => 'Keranjang masih kosong.']);
        }

        $resolved = [];

        foreach ($merged as $key => $quantity) {
            [$productId, $variantId] = array_map('intval', explode('-', (string) $key));
            $variantId = $variantId === 0 ? null : $variantId;

            $product = Product::query()
                ->where('shop_id', $shopId)
                ->whereKey($productId)
                ->first();

            if ($product === null) {
                throw ValidationException::withMessages(['items' => 'Produk tidak ditemukan pada toko Anda.']);
            }

            $price = Money::of($product->effective_price)->maxZero();
            $subTotal = $price->multiply($quantity);
            $tax = Money::of($product->tax)->maxZero()->isZero()
                ? Money::zero()
                : Money::of($subTotal->multiply(Money::of($product->tax)->toFloat())->toFloat());

            if ($product->tax_type === 'exclusive') {
                $tax = Money::of($subTotal->multiply(Money::of($product->tax)->toFloat())->toFloat());
            }

            $resolved[] = [
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
                'price' => $price,
                'tax' => $tax,
                'discount' => Money::zero(),
                'sub_total' => $subTotal,
            ];
        }

        return $resolved;
    }

    private function deduct(Product $product, int $quantity, int $orderId): void
    {
        $available = (int) $product->fresh()->current_stock;

        if ($available < $quantity) {
            throw ValidationException::withMessages([
                'items' => 'Stok '.$product->name.' tidak cukup. Tersedia '.$available.', diminta '.$quantity.'.',
            ]);
        }

        $product->increment('current_stock', -$quantity);

        DB::table('stock_movements')->insert([
            'warehouse_id' => null,
            'product_id' => $product->getKey(),
            'product_variant_id' => null,
            'type' => 'out',
            'quantity' => $quantity,
            'balance_after' => $available - $quantity,
            'reference_type' => 'order',
            'reference_id' => $orderId,
            'note' => 'Penjualan POS',
            'created_by' => $this->scope->userId(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function orderNumber(bool $hold): string
    {
        $prefix = $hold ? 'HOLD' : 'POS';

        do {
            $number = $prefix.'-'.now()->format('YmdHis').'-'.strtoupper(Str::random(6));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
