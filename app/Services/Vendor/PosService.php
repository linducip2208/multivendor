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
            $discountTotal = Money::zero();

            foreach ($lines as $line) {
                $subTotal = $subTotal->add($line['sub_total']);
                $taxTotal = $taxTotal->add($line['tax']);
                $discountTotal = $discountTotal->add($line['discount']);
            }

            $discount = Money::of($payload['discount'] ?? 0)->maxZero()->min($subTotal);
            $total = $subTotal->add($taxTotal)->subtract($discountTotal)->subtract($discount)->maxZero();

            $order = Order::query()->create([
                'order_number' => $this->orderNumber($hold),
                'customer_id' => $this->scope->userId(),
                'shop_id' => $shop->getKey(),
                'sub_total' => $subTotal->toDecimal(),
                'tax' => $taxTotal->toDecimal(),
                'discount' => $discount->add($discountTotal)->toDecimal(),
                'total' => $total->toDecimal(),
                'shipping_cost' => Money::zero()->toDecimal(),
                'payment_method' => VendorScope::clean($payload['payment_method'] ?? 'cash', 40),
                'payment_status' => $hold ? 'unpaid' : 'paid',
                'order_status' => $hold ? OrderStatus::Pending->stored() : OrderStatus::Delivered->stored(),
                'fulfillment_status' => $hold ? 'unfulfilled' : 'fulfilled',
                'source' => 'pos',
                'pos_shift_id' => isset($payload['pos_shift_id']) ? (int) $payload['pos_shift_id'] : null,
                'pos_register_id' => isset($payload['pos_register_id']) ? (int) $payload['pos_register_id'] : null,
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

            if (! isset($merged[$key])) {
                $merged[$key] = ['quantity' => 0, 'discount' => 0.0, 'tax_rate' => null];
            }

            $merged[$key]['quantity'] += $quantity;
            $merged[$key]['discount'] += max(0.0, (float) ($item['discount'] ?? 0));

            if ($merged[$key]['tax_rate'] === null && isset($item['tax_rate']) && is_numeric($item['tax_rate'])) {
                $merged[$key]['tax_rate'] = (float) $item['tax_rate'];
            }
        }

        if ($merged === []) {
            throw ValidationException::withMessages(['items' => 'Keranjang masih kosong.']);
        }

        $resolved = [];

        foreach ($merged as $key => $row) {
            $quantity = (int) $row['quantity'];
            [$productId, $variantId] = array_map('intval', explode('-', (string) $key));
            $variantId = $variantId === 0 ? null : $variantId;

            $product = Product::query()
                ->where('shop_id', $shopId)
                ->whereKey($productId)
                ->first();

            if ($product === null) {
                throw ValidationException::withMessages(['items' => 'Produk tidak ditemukan pada toko Anda.']);
            }

            $price = Money::of($product->getEffectivePrice())->maxZero();
            $subTotal = $price->multiply($quantity);
            // Diskon per item (kolom order_items.discount existing) + pajak per item.
            $itemDiscount = Money::of($row['discount'] ?? 0)->maxZero()->min($subTotal);
            $net = $subTotal->subtract($itemDiscount);
            $taxRate = $row['tax_rate'] !== null
                ? max(0.0, (float) $row['tax_rate'])
                : max(0.0, (float) $product->tax);
            $tax = $net->isPositive() && $taxRate > 0 ? $net->multiply($taxRate / 100) : Money::zero();

            $resolved[] = [
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
                'price' => $price,
                'tax' => $tax,
                'discount' => $itemDiscount,
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

    /** Buka shift kasir (pos_shifts existing) dengan lock anti-duplikat. */
    public function openShift(int $registerId, float $openingCash, ?string $note = null): \App\Models\PosShift
    {
        return DB::transaction(function () use ($registerId, $openingCash, $note): \App\Models\PosShift {
            $existing = \App\Models\PosShift::query()
                ->where('pos_register_id', $registerId)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            return \App\Models\PosShift::query()->create([
                'pos_register_id' => $registerId,
                'cashier_id' => $this->scope->userId(),
                'status' => 'open',
                'opening_cash' => Money::of($openingCash)->maxZero()->toDecimal(),
                'expected_cash' => Money::of($openingCash)->maxZero()->toDecimal(),
                'counted_cash' => 0,
                'variance' => 0,
                'sales_total' => 0,
                'transaction_count' => 0,
                'note' => $note !== null ? VendorScope::clean($note, 500) : null,
                'opened_at' => now(),
            ]);
        }, 3);
    }

    /** Tutup shift + hitung selisih kas (counted vs expected). */
    public function closeShift(\App\Models\PosShift $shift, float $countedCash, ?string $note = null): \App\Models\PosShift
    {
        return DB::transaction(function () use ($shift, $countedCash, $note): \App\Models\PosShift {
            $locked = \App\Models\PosShift::query()->lockForUpdate()->findOrFail($shift->getKey());

            abort_if((string) $locked->status !== 'open', 422, 'Shift sudah ditutup.');

            $sales = Money::of(Order::query()->where('pos_shift_id', $locked->getKey())->where('payment_status', 'paid')->sum('total'));
            $expected = Money::of($locked->opening_cash)->add($sales);
            $counted = Money::of($countedCash)->maxZero();

            $locked->forceFill([
                'status' => 'closed',
                'sales_total' => $sales->toDecimal(),
                'transaction_count' => (int) Order::query()->where('pos_shift_id', $locked->getKey())->count(),
                'expected_cash' => $expected->toDecimal(),
                'counted_cash' => $counted->toDecimal(),
                'variance' => $counted->subtract($expected)->toDecimal(),
                'note' => $note !== null ? VendorScope::clean($note, 500) : $locked->note,
                'closed_at' => now(),
            ])->save();

            return $locked->fresh();
        }, 3);
    }

    /** Retur POS kembali ke stok (increment + movement type=return). */
    public function returnToStock(OrderItem $item, int $quantity, ?string $note = null): OrderItem
    {
        return DB::transaction(function () use ($item, $quantity, $note): OrderItem {
            $locked = OrderItem::query()->lockForUpdate()->findOrFail($item->getKey());
            $order = Order::query()->lockForUpdate()->findOrFail($locked->order_id);

            abort_if((int) $order->shop_id !== $this->scope->shopId(), 403);

            $quantity = max(1, min($quantity, (int) $locked->quantity));

            $product = Product::query()->where('shop_id', $this->scope->shopId())->lockForUpdate()->find($locked->product_id);

            if ($product) {
                $product->increment('current_stock', $quantity);

                DB::table('stock_movements')->insert([
                    'warehouse_id' => null,
                    'product_id' => $product->getKey(),
                    'product_variant_id' => $locked->product_variant_id,
                    'type' => 'return',
                    'quantity' => $quantity,
                    'balance_after' => (int) $product->current_stock + $quantity,
                    'reference_type' => 'order',
                    'reference_id' => $order->getKey(),
                    'note' => 'Retur POS ke stok. '.VendorScope::clean($note ?? '', 180),
                    'created_by' => $this->scope->userId(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $locked->order->statusHistory()->create([
                'status' => (string) $order->order_status,
                'changed_by' => $this->scope->userId(),
                'note' => 'Retur POS '.$quantity.' unit kembali ke stok.',
            ]);

            return $locked->fresh();
        }, 3);
    }

    /** Data barkode massal dari sku/barcode existing. */
    public function barcodeRows(array $productIds): array
    {
        return Product::query()
            ->where('shop_id', $this->scope->shopId())
            ->whereIn('id', array_values(array_unique(array_map('intval', $productIds))))
            ->get(['id', 'name', 'sku', 'barcode', 'price'])
            ->map(fn (Product $p): array => [
                'id' => (int) $p->getKey(),
                'nama' => (string) $p->name,
                'sku' => (string) ($p->sku ?: 'SKU-'.$p->getKey()),
                'barcode' => (string) ($p->barcode ?: $p->sku ?: ('P'.$p->getKey())),
                'harga' => (float) $p->price,
            ])
            ->all();
    }
}
