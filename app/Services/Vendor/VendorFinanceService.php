<?php

declare(strict_types=1);

namespace App\Services\Vendor;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\VendorWithdrawRequest;
use App\Models\VatTax;
use App\Models\WalletTransaction;
use App\Models\SystemSetting;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\DateRange;
use App\Services\Finance\LedgerService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revenue, commission and payout reporting.
 *
 * The three finance screens answer three different questions and must not
 * disagree: revenue is what the shopper paid, commission is what the platform
 * took from it, and payouts are what actually left the wallet.
 */
final class VendorFinanceService
{
    public function __construct(private readonly VendorScope $scope) {}

    public function revenue(DateRange $range): array
    {
        $shopId = $this->scope->shopId();
        $previous = $this->previous($range);

        $orders = $this->revenueOrders($shopId, $range);

        $gross = (float) $orders->clone()->sum('sub_total');
        $tax = (float) $orders->clone()->sum('tax');
        $shipping = (float) $orders->clone()->sum('shipping_cost');
        $discount = Money::of((float) $orders->clone()->sum('discount'))->add((float) $orders->clone()->sum('coupon_discount'));
        $count = (int) $orders->clone()->count();
        $commission = (float) $this->commissionFor($shopId, $range);
        $net = Money::of($gross)->add($tax)->add($shipping)->subtract($discount->toFloat())->subtract($commission)->maxZero();

        $previousGross = (float) $this->revenueOrders($shopId, $previous)->sum('sub_total');
        $previousCount = (int) $this->revenueOrders($shopId, $previous)->count();

        return [
            'range' => $range,
            'gross' => Money::of($gross),
            'tax' => Money::of($tax),
            'shipping' => Money::of($shipping),
            'discount' => $discount,
            'commission' => Money::of($commission),
            'net' => $net,
            'orders' => $count,
            'aov' => $count > 0 ? Money::of($gross)->multiply(1 / $count) : Money::zero(),
            'growth' => $this->growth($gross, $previousGross),
            'order_growth' => $this->growth((float) $count, (float) $previousCount),
            'series' => $this->series($shopId, $range),
            'sources' => $this->sources($shopId, $range),
        ];
    }

    public function commission(): array
    {
        $shop = $this->scope->shop();
        $range = DateRange::fromRequest(request());
        $shopId = (int) $shop->getKey();

        $rows = Transaction::query()
            ->where('shop_id', $shopId)
            ->whereIn('status', AnalyticsService::successfulTransactionStatuses())
            ->where('created_at', '>=', $range->from)
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $total = (float) $this->commissionFor($shopId, $range);
        $vendorShare = Money::sum($rows->getCollection()->pluck('vendor_amount'));
        $gross = Money::sum($rows->getCollection()->pluck('amount'));

        return [
            'range' => $range,
            'shop' => $shop,
            'rate' => $this->rateLabel($shop),
            'total' => Money::of($total),
            'gross' => $gross,
            'vendor_share' => $vendorShare,
            'effective' => $gross->isPositive()
                ? round(($total / $gross->toFloat()) * 100, 2)
                : 0.0,
            'transactions' => $rows,
            'monthly' => $this->monthlyCommission($shopId),
        ];
    }

    public function payouts(): array
    {
        $shopId = $this->scope->shopId();
        $walletId = $this->walletId();

        $requests = VendorWithdrawRequest::query()
            ->where('shop_id', $shopId)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $movements = WalletTransaction::query()
            ->where('wallet_id', $walletId)
            ->where('type', 'withdraw')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return [
            'requests' => $requests,
            'movements' => $movements,
            'pending' => Money::sum($requests->getCollection()->whereIn('status', ['pending', 'processing'])->pluck('amount')),
            'paid' => Money::sum($requests->getCollection()->whereIn('status', ['paid', 'success', 'completed'])->pluck('amount')),
            'rejected' => Money::sum($requests->getCollection()->whereIn('status', ['rejected', 'failed'])->pluck('amount')),
            'available' => Money::of(DB::table('wallets')->where('id', $walletId)->value('balance') ?? 0),
            'bank_account' => VendorScope::maskAccount((string) ($this->shopRow()->bank_account_number ?? '')),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function series(int $shopId, DateRange $range): array
    {
        $days = min(90, $range->days());
        $start = $range->to->subDays($days - 1)->startOfDay();
        $end = $range->to;

        $rows = $this->revenueOrders($shopId, $this->window($start, $end))
            ->get(['orders.created_at', 'orders.sub_total', 'orders.tax'])
            ->groupBy(fn (Order $order): string => $order->created_at->toDateString());

        $series = [];

        for ($day = 0; $day < $days; $day++) {
            $date = $start->addDays($day);
            $bucket = $rows->get($date->toDateString());

            $series[] = [
                'date' => $date->toDateString(),
                'label' => $date->format('d M'),
                'revenue' => (float) ($bucket?->sum('sub_total') ?? 0),
                'orders' => (int) ($bucket?->count() ?? 0),
            ];
        }

        return $series;
    }

    /** @return list<array<string, mixed>> */
    private function sources(int $shopId, DateRange $range): array
    {
        $rows = $this->revenueOrders($shopId, $range)
            ->whereNotNull('coupon_code')
            ->groupBy('coupon_code')
            ->selectRaw('coupon_code, COUNT(*) as orders, SUM(sub_total) as revenue')
            ->orderByDesc('revenue')
            ->limit(6)
            ->get();

        $direct = $this->revenueOrders($shopId, $range)->whereNull('coupon_code');

        $out = [[
            'label' => 'Penjualan langsung',
            'orders' => (int) $direct->count(),
            'revenue' => Money::of((float) $direct->sum('sub_total')),
        ]];

        foreach ($rows as $row) {
            $out[] = [
                'label' => 'Kode: '.$row->coupon_code,
                'orders' => (int) $row->orders,
                'revenue' => Money::of($row->revenue),
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function monthlyCommission(int $shopId): array
    {
        $from = CarbonImmutable::now()->subMonths(11)->startOfMonth();
        $to = CarbonImmutable::now()->endOfMonth();

        $rows = Transaction::query()
            ->where('shop_id', $shopId)
            ->whereIn('status', AnalyticsService::successfulTransactionStatuses())
            ->whereBetween('created_at', [$from, $to])
            ->get(['created_at', 'admin_commission', 'vendor_amount']);

        $grouped = $rows->groupBy(fn (Transaction $t): string => $t->created_at->format('Y-m'));

        $out = [];

        for ($month = 0; $month < 12; $month++) {
            $date = $from->addMonths($month);
            $bucket = $grouped->get($date->format('Y-m'));

            $out[] = [
                'label' => $date->format('M Y'),
                'commission' => (float) ($bucket?->sum('admin_commission') ?? 0),
                'payout' => (float) ($bucket?->sum('vendor_amount') ?? 0),
            ];
        }

        return $out;
    }

    private function commissionFor(int $shopId, DateRange $range): float
    {
        return (float) Transaction::query()
            ->where('shop_id', $shopId)
            ->whereIn('status', AnalyticsService::successfulTransactionStatuses())
            ->whereBetween('created_at', [$range->from, $range->to])
            ->sum('admin_commission');
    }

    private function revenueOrders(int $shopId, DateRange $range)
    {
        return Order::query()
            ->where('shop_id', $shopId)
            ->whereBetween('created_at', [$range->from, $range->to])
            ->whereIn('order_status', AnalyticsService::revenueOrderStatuses())
            ->whereIn('payment_status', AnalyticsService::paidPaymentStatuses());
    }

    private function previous(DateRange $range): DateRange
    {
        return $range->previous();
    }

    private function window(CarbonImmutable $from, CarbonImmutable $to): DateRange
    {
        return DateRange::fromRequest(
            \Illuminate\Http\Request::create('/', 'GET', [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'range' => 'custom',
            ])
        );
    }

    /** @return array{value: float, direction: string} */
    private function growth(float $current, float $previous): array
    {
        if ($previous == 0.0) {
            return ['value' => $current > 0 ? 100.0 : 0.0, 'direction' => $current > 0 ? 'up' : 'flat'];
        }

        $value = round((($current - $previous) / abs($previous)) * 100, 1);

        return [
            'value' => $value,
            'direction' => $value > 0.05 ? 'up' : ($value < -0.05 ? 'down' : 'flat'),
        ];
    }

    private function walletId(): int
    {
        return (int) DB::table('wallets')
            ->where('user_id', $this->scope->userId())
            ->value('id');
    }

    private function shopRow(): object
    {
        return DB::table('shops')->where('id', $this->scope->shopId())->first()
            ?? (object) ['bank_account_number' => null];
    }

    private function rateLabel(object $shop): string
    {
        $value = (float) ($shop->commission_value ?? 0);

        return ($shop->commission_type ?? 'percentage') === 'percentage'
            ? rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',').'%'
            : Money::of($value)->toDecimal();
    }

    /** @return Collection<int, Transaction> */
    public function recentTransactions(int $limit = 10): Collection
    {
        return Transaction::query()
            ->where('shop_id', $this->scope->shopId())
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Dashboard selisih rekonsiliasi: grup pending/failed + callback mismatch,
     * memakai kolom existing (last_reconciled_at, reconciliation_note, dst).
     */
    public function reconciliationOverview(int $limit = 25): array
    {
        $shopId = $this->scope->shopId();

        $mismatches = \App\Models\PaymentWebhookCallback::query()
            ->where('processing_result', 'amount_mismatch')
            ->whereHas('paymentGroup.orders', fn ($q) => $q->where('shop_id', $shopId))
            ->with(['paymentGroup:id,payment_number,grand_total,status'])
            ->orderByDesc('received_at')
            ->limit($limit)
            ->get();

        $pending = \App\Models\PaymentGroup::query()
            ->whereIn('status', ['pending', 'failed', 'expired'])
            ->whereHas('orders', fn ($q) => $q->where('shop_id', $shopId))
            ->orderBy('expired_at')
            ->limit($limit)
            ->get(['id', 'payment_number', 'grand_total', 'status', 'expired_at', 'last_reconciled_at', 'reconciliation_attempts', 'reconciliation_note']);

        return [
            'mismatches' => $mismatches,
            'pending' => $pending,
            'mismatch_total' => $mismatches->sum(fn ($c): float => (float) ($c->reported_amount ?? 0) - (float) ($c->expected_amount ?? 0)),
        ];
    }

    /** Retry webhook manual: tandai callback agar diproses ulang (idempoten). */
    public function retryWebhook(int $callbackId): \App\Models\PaymentWebhookCallback
    {
        return DB::transaction(function () use ($callbackId): \App\Models\PaymentWebhookCallback {
            $callback = \App\Models\PaymentWebhookCallback::query()->lockForUpdate()->findOrFail($callbackId);

            $group = $callback->paymentGroup()->lockForUpdate()->first();

            abort_if($group === null || ! $group->orders()->where('shop_id', $this->scope->shopId())->exists(), 403);

            $callback->forceFill([
                'processing_result' => 'received',
                'processed_at' => null,
                'status' => $callback->status,
            ])->save();

            $group->forceFill([
                'last_reconciled_at' => now(),
                'reconciliation_attempts' => ((int) $group->reconciliation_attempts) + 1,
                'reconciliation_note' => 'Retry webhook manual oleh vendor.',
            ])->save();

            return $callback->fresh();
        }, 3);
    }

    // ── Perdalaman keuangan: e-Faktur, held vs tersedia, L/R (aditif) ──

    /** Tarif PPN aktif: VatTax pertama, fallback pengaturan, default 11%. */
    public function activeTaxRate(): float
    {
        try {
            $rate = VatTax::where('is_active', true)->orderBy('id')->value('rate');
            if ($rate !== null) {
                return (float) $rate;
            }
        } catch (\Throwable) {
        }

        return (float) (SystemSetting::get('tax_default_rate', '11') ?: 11);
    }

    /**
     * Hitung DPP/PPN e-Faktur dari kolom tax existing.
     *
     * @return array{invoice_number: string, tax_serial: string, dpp: float, ppn_rate: float, ppn: float, grand_total: float}
     */
    public function taxCalculation(Order $order): array
    {
        $rate = $this->activeTaxRate();
        $subtotal = (float) $order->sub_total;
        $include = (bool) (SystemSetting::get('tax_include_in_price') ?: false);

        if ($include && $rate > 0) {
            $dpp = round($subtotal * 100 / (100 + $rate), 2);
            $ppn = round($subtotal - $dpp, 2);
        } else {
            $dpp = round($subtotal, 2);
            $ppn = (float) $order->tax > 0 ? round((float) $order->tax, 2) : round($dpp * $rate / 100, 2);
        }

        return [
            'invoice_number' => $order->invoiceNumber(),
            'tax_serial' => $this->taxSerial($order),
            'dpp' => $dpp,
            'ppn_rate' => $rate,
            'ppn' => $ppn,
            'grand_total' => round($dpp + $ppn + (float) $order->shipping_cost, 2),
        ];
    }

    /** Nomor seri pajak deterministik per order: 010.KODE-YY.XXXXXXXX. */
    public function taxSerial(Order $order): string
    {
        $yy = $order->created_at ? $order->created_at->format('y') : now()->format('y');

        return sprintf('010.%03d-%s.%08d', ((int) $order->shop_id) % 1000, $yy, (int) $order->getKey());
    }

    /**
     * Terbitkan e-Faktur per order (idempoten) + jurnal PPN seimbang.
     *
     * @return array<string, mixed>
     */
    public function issueTaxInvoice(Order $order): array
    {
        $calc = $this->taxCalculation($order->fresh() ?? $order);

        if (! Schema::hasTable('tax_invoices')) {
            return array_merge($calc, ['id' => null, 'status' => 'issued']);
        }

        $row = DB::table('tax_invoices')->updateOrInsert(
            ['order_id' => $order->id],
            [
                'shop_id' => $order->shop_id,
                'invoice_number' => $calc['invoice_number'],
                'tax_serial' => $calc['tax_serial'],
                'dpp' => $calc['dpp'],
                'ppn_rate' => $calc['ppn_rate'],
                'ppn' => $calc['ppn'],
                'grand_total' => $calc['grand_total'],
                'status' => 'issued',
                'issued_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        app(LedgerService::class)->postTaxInvoice(
            $order, (float) $calc['ppn'], (string) $calc['tax_serial'], 'tax-invoice:'.$order->getKey(),
        );

        $record = DB::table('tax_invoices')->where('order_id', $order->id)->first();

        return array_merge($calc, [
            'id' => $record->id ?? null,
            'status' => (string) ($record->status ?? 'issued'),
            'saved' => $row,
        ]);
    }

    /** Tahan dana order ke pending_balance (held). Idempoten per order. */
    public function holdForOrder(Order $order, ?float $amount = null): WalletTransaction
    {
        $shop = $order->shop()->firstOrFail();
        $wallet = \App\Models\Wallet::query()->firstOrCreate(
            ['user_id' => $shop->vendor_id], ['balance' => 0, 'pending_balance' => 0],
        );

        $nominal = $amount ?? $this->vendorShareFor($order);
        $tx = $wallet->holdPending(
            $nominal,
            'Dana ditahan order #'.$order->order_number,
            'order_hold',
            (int) $order->getKey(),
            'hold:settlement:'.$order->getKey(),
        );

        if (Schema::hasColumn('orders', 'held_amount')) {
            $order->forceFill(['held_amount' => $nominal])->save();
        }

        return $tx;
    }

    /**
     * Rilis otomatis saat order completed/delivered + riwayat.
     * Idempoten: rilis kedua mengembalikan transaksi yang sama.
     */
    public function releaseOnCompleted(Order $order): ?WalletTransaction
    {
        $order = $order->fresh() ?? $order;

        if (! in_array((string) $order->order_status, ['completed', 'delivered'], true)) {
            return null;
        }

        $held = Schema::hasColumn('orders', 'held_amount') ? (float) ($order->held_amount ?? 0) : 0.0;
        if ($held <= 0) {
            $existing = WalletTransaction::query()
                ->where('reference_key', 'release:settlement:'.$order->getKey())
                ->first();

            return $existing;
        }

        $shop = $order->shop()->firstOrFail();
        $wallet = \App\Models\Wallet::query()->firstOrCreate(
            ['user_id' => $shop->vendor_id], ['balance' => 0, 'pending_balance' => 0],
        );

        $tx = $wallet->releaseHeld(
            $held,
            'Dana cair order #'.$order->order_number,
            'order_settlement',
            (int) $order->getKey(),
            'release:settlement:'.$order->getKey(),
        );

        if (Schema::hasColumn('orders', 'held_amount')) {
            $order->forceFill(['held_amount' => 0, 'settled_at' => now()])->save();
        }

        return $tx;
    }

    /** Pisah saldo tersedia vs tertahan + riwayat hold/release toko. */
    public function heldBalance(int $limit = 20): array
    {
        $wallet = \App\Models\Wallet::query()->firstOrCreate(
            ['user_id' => $this->scope->userId()], ['balance' => 0, 'pending_balance' => 0],
        );

        $history = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->whereIn('operation', ['hold', 'release', 'withdraw'])
            ->orderByDesc('id')
            ->limit(max(1, min(100, $limit)))
            ->get();

        return array_merge($wallet->heldVsAvailable(), ['history' => $history]);
    }

    /**
     * Laba/rugi per toko per periode.
     *
     * @return array<string, mixed>
     */
    public function profitLoss(int $shopId, string $from, string $to): array
    {
        $orders = Order::query()
            ->where('shop_id', $shopId)
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('order_status', AnalyticsService::revenueOrderStatuses())
            ->orderBy('created_at')
            ->get();

        $commissionByOrder = Transaction::query()
            ->where('shop_id', $shopId)
            ->whereIn('status', AnalyticsService::successfulTransactionStatuses())
            ->whereBetween('created_at', [$from, $to])
            ->pluck('admin_commission', 'order_id');

        $gross = (float) $orders->sum('sub_total');
        $tax = (float) $orders->sum('tax');
        $shipping = (float) $orders->sum('shipping_cost');
        $discount = (float) $orders->sum('discount') + (float) $orders->sum('coupon_discount');
        $commission = (float) $commissionByOrder->sum();
        $refunded = (float) $orders->sum('refunded_amount');

        $rows = $orders->map(fn (Order $o): array => [
            'order_number' => (string) $o->order_number,
            'date' => (string) ($o->created_at?->format('Y-m-d') ?? ''),
            'gross' => (float) $o->sub_total,
            'tax' => (float) $o->tax,
            'shipping' => (float) $o->shipping_cost,
            'discount' => (float) $o->discount + (float) $o->coupon_discount,
            'commission' => (float) ($commissionByOrder[$o->id] ?? 0),
            'net' => max(0.0, (float) $o->sub_total + (float) $o->tax + (float) $o->shipping_cost - (float) $o->discount - (float) $o->coupon_discount - (float) ($commissionByOrder[$o->id] ?? 0)),
        ])->all();

        return [
            'shop_id' => $shopId,
            'from' => $from,
            'to' => $to,
            'orders' => $orders->count(),
            'gross' => $gross,
            'tax' => $tax,
            'shipping' => $shipping,
            'discount' => $discount,
            'commission' => $commission,
            'refunded' => $refunded,
            'net' => max(0.0, $gross + $tax + $shipping - $discount - $commission),
            'rows' => $rows,
        ];
    }

    /** Ekspor CSV akuntansi dari L/R (tanpa route baru: unduh via data-URI di view). */
    public function accountingCsv(int $shopId, string $from, string $to): string
    {
        $pl = $this->profitLoss($shopId, $from, $to);

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['Nomor Pesanan', 'Tanggal', 'Bruto', 'Pajak', 'Ongkir', 'Diskon', 'Komisi', 'Bersih']);
        foreach ($pl['rows'] as $row) {
            fputcsv($stream, [
                $row['order_number'], $row['date'], $row['gross'], $row['tax'],
                $row['shipping'], $row['discount'], $row['commission'], $row['net'],
            ]);
        }
        fputcsv($stream, ['TOTAL', $pl['from'].' s/d '.$pl['to'], $pl['gross'], $pl['tax'], $pl['shipping'], $pl['discount'], $pl['commission'], $pl['net']]);
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    private function vendorShareFor(Order $order): float
    {
        $tx = Transaction::query()->where('order_id', $order->id)->first();

        if ($tx) {
            return max(0.0, (float) $tx->vendor_amount);
        }

        return max(0.0, (float) $order->sub_total + (float) $order->tax - (float) $order->discount - (float) $order->coupon_discount);
    }
}
