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
use Illuminate\Support\Facades\Schema;
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

    /** Metode yang boleh dipakai pada split tender. */
    public const TENDER_METHODS = ['cash', 'qris', 'transfer', 'debit', 'ewallet'];

    /** @param  array<int, array<string, mixed>>  $lines */
    public function sell(array $payload): Order
    {
        return DB::transaction(function () use ($payload): Order {
            $shop = Shop::query()->lockForUpdate()->findOrFail($this->scope->shopId());
            $hold = (bool) ($payload['hold'] ?? false);

            // Idempotency kasir: kunci yang sama tidak boleh mencetak dua
            // struk (double-tap tombol Bayar). Replay mengembalikan order
            // existing apa adanya.
            $idempotencyKey = isset($payload['idempotency_key']) && is_string($payload['idempotency_key'])
                ? trim($payload['idempotency_key'])
                : '';

            if ($idempotencyKey !== '' && Schema::hasColumn('orders', 'idempotency_key')) {
                $replay = Order::query()
                    ->where('shop_id', $shop->getKey())
                    ->where('idempotency_key', mb_substr($idempotencyKey, 0, 80))
                    ->lockForUpdate()
                    ->first();

                if ($replay) {
                    return $replay->refresh(['items']);
                }
            }

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

            // Split tender: tunai + QRIS dst. dalam satu struk. Total tender
            // harus pas dengan total belanja (selisih sekecil apa pun
            // ditolak agar kas tidak selisih). Hold order boleh tanpa tender.
            $tenders = $this->resolveTenders($payload['tenders'] ?? null, $total, $hold);
            $paymentMethod = $tenders !== []
                ? 'split'
                : VendorScope::clean($payload['payment_method'] ?? 'cash', 40);

            $phone = isset($payload['customer_phone']) && is_string($payload['customer_phone'])
                ? preg_replace('/\D+/', '', $payload['customer_phone'])
                : '';

            $order = Order::query()->create([
                'order_number' => $this->orderNumber($hold),
                'customer_id' => $this->scope->userId(),
                'shop_id' => $shop->getKey(),
                'sub_total' => $subTotal->toDecimal(),
                'tax' => $taxTotal->toDecimal(),
                'discount' => $discount->add($discountTotal)->toDecimal(),
                'total' => $total->toDecimal(),
                'shipping_cost' => Money::zero()->toDecimal(),
                'payment_method' => $paymentMethod,
                'payment_status' => $hold ? 'unpaid' : 'paid',
                'order_status' => $hold ? OrderStatus::Pending->stored() : OrderStatus::Delivered->stored(),
                'fulfillment_status' => $hold ? 'unfulfilled' : 'fulfilled',
                'source' => 'pos',
                'pos_shift_id' => isset($payload['pos_shift_id']) ? (int) $payload['pos_shift_id'] : null,
                'pos_register_id' => isset($payload['pos_register_id']) ? (int) $payload['pos_register_id'] : null,
                'note' => 'POS: '.VendorScope::clean($payload['customer_name'] ?? 'Pelanggan walk-in', 200),
            ]);

            // Kolom aditif order POS (dijaga hasColumn agar rollback/migrasi
            // parsial tidak merusak transaksi kasir yang sedang berjalan).
            $additive = [];

            if ($idempotencyKey !== '' && Schema::hasColumn('orders', 'idempotency_key')) {
                $additive['idempotency_key'] = mb_substr($idempotencyKey, 0, 80);
            }

            if (Schema::hasColumn('orders', 'receipt_token')) {
                $additive['receipt_token'] = Str::random(32);
            }

            if ($phone !== '' && Schema::hasColumn('orders', 'pos_customer_phone')) {
                $additive['pos_customer_phone'] = mb_substr($phone, 0, 20);
            }

            if ($additive !== []) {
                $order->forceFill($additive)->save();
            }

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
                'note' => $hold
                    ? 'POS hold order'
                    : ($tenders !== []
                        ? 'POS sale ('.$this->tenderSummary($tenders).')'
                        : 'POS sale'),
            ]);

            if ($tenders !== []) {
                $this->persistTenders($order->getKey(), $tenders);
            }

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

            // Struk digital juga berlaku untuk hold yang dilanjutkan: terbitkan
            // token bila order lama belum memilikinya.
            if (Schema::hasColumn('orders', 'receipt_token')
                && (string) ($locked->getAttribute('receipt_token') ?? '') === '') {
                $locked->forceFill(['receipt_token' => Str::random(32)])->save();
            }

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
     * Rincian tender untuk struk: dari pos_payments bila ada, bila tidak
     * jatuh kembali ke metode tunggal legacy (satu baris sebesar total).
     *
     * @return list<array{method: string, amount: float}>
     */
    public function tendersFor(Order $order): array
    {
        if (Schema::hasTable('pos_payments')) {
            $rows = DB::table('pos_payments')
                ->where('order_id', $order->getKey())
                ->orderBy('id')
                ->get(['method', 'amount']);

            if ($rows->isNotEmpty()) {
                return $rows->map(fn ($row) => [
                    'method' => (string) $row->method,
                    'amount' => (float) $row->amount,
                ])->all();
            }
        }

        return [[
            'method' => (string) ($order->payment_method ?: 'cash'),
            'amount' => (float) $order->total,
        ]];
    }

    /** Tautan struk digital (membawa token verifikasi bila kolom tersedia). */
    public function receiptUrl(Order $order): string
    {
        $base = route('vendor.pos.print', $order->getKey());

        if (Schema::hasColumn('orders', 'receipt_token')) {
            $token = (string) ($order->getAttribute('receipt_token') ?? '');

            if ($token !== '') {
                return $base.'?token='.$token;
            }
        }

        return $base;
    }

    /**
     * Verifikasi token struk: cocok bila order belum bertoken (legacy) atau
     * token query sama dengan token tersimpan.
     */
    public function receiptVerifyOk(Order $order, ?string $token): bool
    {
        if (! Schema::hasColumn('orders', 'receipt_token')) {
            return true;
        }

        $stored = (string) ($order->getAttribute('receipt_token') ?? '');

        if ($stored === '') {
            return true;
        }

        return $token !== null && hash_equals($stored, trim($token));
    }

    /** Tautan WA struk digital; null bila nomor pelanggan tidak ada. */
    public function waLink(Order $order): ?string
    {
        $digits = $this->normalizePhone(
            (string) ($order->getAttribute('pos_customer_phone') ?? '')
        );

        if ($digits === null) {
            return null;
        }

        $text = rawurlencode(
            'Struk belanja '.$order->order_number.' ('.\App\Support\Currency::format((float) $order->total).'). Verifikasi: '.$this->receiptUrl($order)
        );

        return 'https://wa.me/'.$digits.'?text='.$text;
    }

    /**
     * Tandai struk digital dibagikan (mis. WA terkirim/diketuk kasir).
     * Idempoten: pemanggilan kedua tidak mengubah stempel pertama.
     */
    public function markReceiptShared(Order $order, ?string $channel = null, ?int $actorId = null): Order
    {
        return DB::transaction(function () use ($order, $channel, $actorId): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            abort_if((int) $locked->shop_id !== $this->scope->shopId(), 403);

            if (! Schema::hasColumn('orders', 'digital_receipt_sent_at')) {
                return $locked;
            }

            if ($locked->getAttribute('digital_receipt_sent_at') !== null) {
                return $locked;
            }

            $label = $channel !== null && trim($channel) !== ''
                ? VendorScope::clean($channel, 20)
                : 'wa';

            $locked->forceFill(['digital_receipt_sent_at' => now()])->save();

            OrderStatusHistory::query()->create([
                'order_id' => $locked->getKey(),
                'status' => (string) $locked->order_status,
                'changed_by' => $actorId ?? $this->scope->userId(),
                'note' => 'Struk digital dibagikan via '.$label.'.',
            ]);

            return $locked->fresh(['items']);
        }, 3);
    }

    /** Normalisasi nomor Indonesia ke format wa.me (62...). Null bila invalid. */
    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        if (! preg_match('/^62[0-9]{8,14}$/', $digits)) {
            return null;
        }

        return $digits;
    }

    /**
     * Validasi split tender: metode dikenal, nominal positif, dan jumlah
     * semua tender harus pas dengan total (tanpa toleransi selisih).
     *
     * @return list<array{method: string, amount: Money}>
     */
    private function resolveTenders(mixed $tenders, Money $total, bool $hold): array
    {
        if ($tenders === null) {
            return [];
        }

        if (! is_array($tenders)) {
            throw ValidationException::withMessages(['tenders' => 'Rincian pembayaran tidak valid.']);
        }

        $rows = array_values(array_filter($tenders, 'is_array'));

        if ($rows === []) {
            return [];
        }

        if (count($rows) > 5) {
            throw ValidationException::withMessages(['tenders' => 'Maksimal 5 metode pembayaran dalam satu struk.']);
        }

        $resolved = [];

        foreach ($rows as $index => $row) {
            $method = strtolower(trim((string) ($row['method'] ?? '')));

            if (! in_array($method, self::TENDER_METHODS, true)) {
                throw ValidationException::withMessages(['tenders' => 'Metode pembayaran baris '.($index + 1).' tidak dikenal.']);
            }

            $amount = Money::of($row['amount'] ?? 0);

            if (! $amount->isPositive()) {
                throw ValidationException::withMessages(['tenders' => 'Nominal baris '.($index + 1).' harus lebih dari nol.']);
            }

            $resolved[] = ['method' => $method, 'amount' => $amount];
        }

        // Gabungkan metode ganda yang sama agar struk rapi.
        $merged = [];

        foreach ($resolved as $row) {
            $merged[$row['method']] = isset($merged[$row['method']])
                ? ['method' => $row['method'], 'amount' => $merged[$row['method']]['amount']->add($row['amount'])]
                : $row;
        }

        $resolved = array_values($merged);
        $paid = Money::zero();

        foreach ($resolved as $row) {
            $paid = $paid->add($row['amount']);
        }

        if ($paid->compare($total) !== 0 && ! $hold) {
            throw ValidationException::withMessages([
                'tenders' => 'Total pembayaran ('.\App\Support\Currency::format($paid->toFloat()).') harus pas dengan total belanja ('.\App\Support\Currency::format($total->toFloat()).').',
            ]);
        }

        return $resolved;
    }

    /** @param  list<array{method: string, amount: Money}>  $tenders */
    private function persistTenders(int $orderId, array $tenders): void
    {
        if (! Schema::hasTable('pos_payments')) {
            return;
        }

        foreach ($tenders as $row) {
            DB::table('pos_payments')->insert([
                'order_id' => $orderId,
                'method' => $row['method'],
                'amount' => $row['amount']->toDecimal(),
                'created_by' => $this->scope->userId(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** @param  list<array{method: string, amount: Money}>  $tenders */
    private function tenderSummary(array $tenders): string
    {
        return implode(' + ', array_map(
            fn (array $row) => $row['method'].' '.\App\Support\Currency::format($row['amount']->toFloat()),
            $tenders,
        ));
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
