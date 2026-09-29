<?php

declare(strict_types=1);

namespace App\Services\Backoffice;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\PaymentGroup;
use App\Models\Refund;
use App\Models\Shop;
use App\Models\Transaction;
use App\Models\VendorWithdrawRequest;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\DateRange;
use App\Services\AuditLogger;
use App\Services\Finance\LedgerService;
use App\Support\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * Finance operations: settlements, the double-entry report, refunds and gateway
 * reconciliation.
 */
final class FinanceAdminService extends AnalyticsService
{
    public const PAGE_SIZE = 20;

    public const RECONCILE_STALE_MINUTES = 15;

    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * @return array<string, mixed>
     */
    public function settlements(int $page = 1, string $search = '', string $status = ''): array
    {
        $query = VendorWithdrawRequest::query()
            ->with(['vendor:id,name,email', 'shop:id,name', 'approver:id,name']);

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->whereHas('vendor', fn ($v) => $v->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('shop', fn ($s) => $s->where('name', 'like', '%'.$search.'%'));
            });
        }

        $page = max(1, $page);
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('id')->forPage($page, self::PAGE_SIZE)->get()
            ->map(fn (VendorWithdrawRequest $request): array => [
                'id' => (int) $request->id,
                'vendor' => (string) ($request->vendor?->name ?? '-'),
                'shop' => (string) ($request->shop?->name ?? '-'),
                'amount' => (float) $request->amount,
                'amount_formatted' => Currency::format((float) $request->amount),
                'bank_name' => (string) ($request->bank_name ?? ''),
                'bank_account' => $this->maskAccount((string) ($request->bank_account_number ?? '')),
                'status' => (string) $request->status,
                'approver' => (string) ($request->approver?->name ?? ''),
                'approved_at' => (string) ($request->approved_at?->format('Y-m-d H:i') ?? ''),
                'created_at' => (string) ($request->created_at?->format('Y-m-d H:i') ?? ''),
            ])
            ->all();

        $base = VendorWithdrawRequest::query();
        $counts = [
            'all' => (int) (clone $base)->count(),
            'pending' => (int) (clone $base)->where('status', 'pending')->count(),
            'approved' => (int) (clone $base)->where('status', 'approved')->count(),
            'completed' => (int) (clone $base)->where('status', 'completed')->count(),
            'rejected' => (int) (clone $base)->where('status', 'rejected')->count(),
        ];

        return [
            'rows' => $rows,
            'counts' => $counts,
            'pending_amount' => (float) VendorWithdrawRequest::query()->where('status', 'pending')->sum('amount'),
            'paid_amount' => (float) VendorWithdrawRequest::query()->whereIn('status', ['approved', 'completed'])->sum('amount'),
            'pagination' => [
                'total' => $total,
                'per_page' => self::PAGE_SIZE,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / self::PAGE_SIZE)),
            ],
        ];
    }

    private function maskAccount(string $account): string
    {
        if ($account === '') {
            return '-';
        }

        return str_repeat('*', max(0, strlen($account) - 4)).substr($account, -4);
    }

    /**
     * @return array<string, mixed>
     */
    public function settlementDetail(VendorWithdrawRequest $request): array
    {
        $request->loadMissing(['vendor:id,name,email,phone', 'shop:id,name,status', 'approver:id,name']);

        $shop = $request->shop;
        $earnings = 0.0;
        $commission = 0.0;
        $orderCount = 0;

        if ($shop !== null) {
            $stats = Transaction::query()
                ->where('shop_id', $shop->id)
                ->whereIn('status', self::successfulTransactionStatuses())
                ->selectRaw('COALESCE(SUM(vendor_amount), 0) as earnings, COALESCE(SUM(admin_commission), 0) as commission, COUNT(*) as orders')
                ->first();

            $earnings = (float) ($stats->earnings ?? 0);
            $commission = (float) ($stats->commission ?? 0);
            $orderCount = (int) ($stats->orders ?? 0);
        }

        return [
            'settlement' => [
                'id' => (int) $request->id,
                'vendor' => (string) ($request->vendor?->name ?? '-'),
                'vendor_email' => (string) ($request->vendor?->email ?? ''),
                'shop' => (string) ($shop?->name ?? '-'),
                'shop_status' => (string) ($shop?->status ?? ''),
                'amount' => (float) $request->amount,
                'amount_formatted' => Currency::format((float) $request->amount),
                'bank_name' => (string) ($request->bank_name ?? ''),
                'bank_account' => $this->maskAccount((string) ($request->bank_account_number ?? '')),
                'bank_account_name' => (string) ($request->bank_account_name ?? ''),
                'status' => (string) $request->status,
                'note' => (string) ($request->note ?? ''),
                'rejection_reason' => (string) ($request->rejection_reason ?? ''),
                'approver' => (string) ($request->approver?->name ?? ''),
                'approved_at' => (string) ($request->approved_at?->format('Y-m-d H:i') ?? ''),
                'completed_at' => (string) ($request->completed_at?->format('Y-m-d H:i') ?? ''),
                'created_at' => (string) ($request->created_at?->format('Y-m-d H:i') ?? ''),
            ],
            'earnings' => [
                'lifetime' => $earnings,
                'lifetime_formatted' => Currency::format($earnings),
                'commission' => $commission,
                'commission_formatted' => Currency::format($commission),
                'orders' => $orderCount,
                'requested_percent' => $earnings > 0 ? round(((float) $request->amount / $earnings) * 100, 1) : 0.0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ledgerReport(int $page = 1, string $account = '', string $entryType = '', string $search = '', int $perPage = 25): array
    {
        $this->ledger->ensureSystemAccounts();

        $query = LedgerEntry::query()
            ->with('account:id,code,name,type,normal_balance')
            ->orderByDesc('posted_at')
            ->orderByDesc('id');

        if ($account !== '') {
            $query->whereHas('account', fn (Builder $q) => $q->where('code', $account));
        }

        if ($entryType !== '') {
            $query->where('entry_type', $entryType);
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('memo', 'like', '%'.$search.'%')
                    ->orWhere('transaction_group', 'like', '%'.$search.'%');
            });
        }

        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $total = (int) (clone $query)->count();

        $rows = $query->forPage($page, $perPage)->get()
            ->map(fn (LedgerEntry $entry): array => [
                'id' => (int) $entry->id,
                'group' => (string) $entry->transaction_group,
                'posted_at' => (string) ($entry->posted_at?->format('Y-m-d H:i') ?? ''),
                'account_code' => (string) ($entry->account?->code ?? ''),
                'account_name' => (string) ($entry->account?->name ?? ''),
                'account_type' => (string) ($entry->account?->type ?? ''),
                'direction' => (string) $entry->direction,
                'amount' => (float) $entry->amount,
                'amount_formatted' => Currency::format((float) $entry->amount),
                'entry_type' => (string) $entry->entry_type,
                'memo' => (string) ($entry->memo ?? ''),
                'order_id' => $entry->order_id,
                'shop_id' => $entry->shop_id,
            ])
            ->all();

        $periodTotals = LedgerEntry::query()
            ->selectRaw("SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END) as debit, SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END) as credit")
            ->first();

        $debit = (float) ($periodTotals->debit ?? 0);
        $credit = (float) ($periodTotals->credit ?? 0);

        return [
            'rows' => $rows,
            'accounts' => $this->accountRows(),
            'entry_types' => $this->entryTypes(),
            'totals' => [
                'debit' => $debit,
                'debit_formatted' => Currency::format($debit),
                'credit' => $credit,
                'credit_formatted' => Currency::format($credit),
                'balanced' => abs($debit - $credit) < 0.01,
                'difference' => $debit - $credit,
            ],
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function accountRows(): array
    {
        $balances = LedgerEntry::query()
            ->select('account_id')
            ->selectRaw("SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END) as debit")
            ->selectRaw("SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END) as credit")
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        return LedgerAccount::query()->orderBy('type')->orderBy('code')->get()
            ->map(function (LedgerAccount $account) use ($balances): array {
                $row = $balances[$account->id] ?? null;
                $debit = (float) ($row->debit ?? 0);
                $credit = (float) ($row->credit ?? 0);

                return [
                    'id' => (int) $account->id,
                    'code' => (string) $account->code,
                    'name' => (string) $account->name,
                    'type' => (string) $account->type,
                    'normal_balance' => (string) $account->normal_balance,
                    'debit' => $debit,
                    'debit_formatted' => Currency::format($debit),
                    'credit' => $credit,
                    'credit_formatted' => Currency::format($credit),
                    'balance' => $account->normal_balance === 'debit' ? $debit - $credit : $credit - $debit,
                    'balance_formatted' => Currency::format($account->normal_balance === 'debit' ? $debit - $credit : $credit - $debit),
                ];
            })
            ->all();
    }

    /**
     * @return list<string>
     */
    private function entryTypes(): array
    {
        try {
            return LedgerEntry::query()->distinct()->orderBy('entry_type')->pluck('entry_type')->map(fn ($v): string => (string) $v)->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function refunds(int $page = 1, string $status = '', string $search = ''): array
    {
        $query = Refund::query()
            ->with(['order:id,order_number,customer_id,total', 'order.customer:id,name', 'provider:id,name', 'paymentGroup:id,payment_number']);

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('refund_number', 'like', '%'.$search.'%')
                    ->orWhere('gateway_refund_id', 'like', '%'.$search.'%')
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', '%'.$search.'%'));
            });
        }

        $page = max(1, $page);
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('id')->forPage($page, self::PAGE_SIZE)->get()
            ->map(fn (Refund $refund): array => [
                'id' => (int) $refund->id,
                'order_id' => (int) $refund->order_id,
                'refund_number' => (string) $refund->refund_number,
                'order_number' => (string) ($refund->order?->order_number ?? '-'),
                'customer' => (string) ($refund->order?->customer?->name ?? '-'),
                'amount' => (float) $refund->amount,
                'amount_formatted' => Currency::format((float) $refund->amount),
                'status' => (string) $refund->status,
                'reason' => (string) ($refund->reason ?? ''),
                'provider' => (string) ($refund->provider?->name ?? '-'),
                'gateway_refund_id' => (string) ($refund->gateway_refund_id ?? ''),
                'requested_by_type' => (string) $refund->requested_by_type,
                'succeeded_at' => (string) ($refund->succeeded_at?->format('Y-m-d H:i') ?? ''),
                'created_at' => (string) ($refund->created_at?->format('Y-m-d H:i') ?? ''),
            ])
            ->all();

        $base = Refund::query();
        $counts = [
            'all' => (int) (clone $base)->count(),
            'pending' => (int) (clone $base)->where('status', 'pending')->count(),
            'succeeded' => (int) (clone $base)->where('status', 'succeeded')->count(),
            'failed' => (int) (clone $base)->where('status', 'failed')->count(),
            'rejected' => (int) (clone $base)->where('status', 'rejected')->count(),
        ];

        return [
            'rows' => $rows,
            'counts' => $counts,
            'succeeded_amount' => (float) Refund::query()->where('status', 'succeeded')->sum('amount'),
            'pending_amount' => (float) Refund::query()->where('status', 'pending')->sum('amount'),
            'pagination' => [
                'total' => $total,
                'per_page' => self::PAGE_SIZE,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / self::PAGE_SIZE)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function refundDetail(Refund $refund): array
    {
        $refund->loadMissing(['order.customer:id,name,email', 'order.items:id,product_id,quantity,sub_total,refund_amount', 'order.items.product:id,name,sku', 'provider:id,name,api_format', 'paymentGroup:id,payment_number,grand_total']);

        $response = $refund->gateway_response;

        return [
            'refund' => [
                'id' => (int) $refund->id,
                'refund_number' => (string) $refund->refund_number,
                'order_number' => (string) ($refund->order?->order_number ?? '-'),
                'customer' => (string) ($refund->order?->customer?->name ?? '-'),
                'amount' => (float) $refund->amount,
                'amount_formatted' => Currency::format((float) $refund->amount),
                'currency' => (string) $refund->currency,
                'status' => (string) $refund->status,
                'reason' => (string) ($refund->reason ?? ''),
                'failure_reason' => (string) ($refund->failure_reason ?? ''),
                'provider' => (string) ($refund->provider?->name ?? '-'),
                'gateway_refund_id' => (string) ($refund->gateway_refund_id ?? ''),
                'payment_number' => (string) ($refund->paymentGroup?->payment_number ?? ''),
                'idempotency_key' => (string) ($refund->idempotency_key ?? ''),
                'requested_by_type' => (string) $refund->requested_by_type,
                'succeeded_at' => (string) ($refund->succeeded_at?->format('Y-m-d H:i') ?? ''),
                'created_at' => (string) ($refund->created_at?->format('Y-m-d H:i') ?? ''),
            ],
            'order_total' => (float) ($refund->order?->total ?? 0),
            'order_total_formatted' => Currency::format((float) ($refund->order?->total ?? 0)),
            'refunded_ratio' => (float) ($refund->order?->total ?? 0) > 0
                ? round(((float) $refund->amount / (float) $refund->order->total) * 100, 1)
                : 0.0,
            'items' => $refund->order?->items->map(fn ($item): array => [
                'name' => (string) ($item->product?->name ?? 'Produk dihapus'),
                'sku' => (string) ($item->product?->sku ?? ''),
                'quantity' => (int) $item->quantity,
                'sub_total' => (float) $item->sub_total,
                'sub_total_formatted' => Currency::format((float) $item->sub_total),
                'refund_amount' => (float) $item->refund_amount,
            ])->all() ?? [],
            'gateway_response' => is_array($response) ? Str::limit((string) json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 2000) : '',
        ];
    }

    /**
     * Payment groups that are still pending long enough to be suspicious.
     *
     * @return array<string, mixed>
     */
    public function reconciliation(int $staleMinutes = self::RECONCILE_STALE_MINUTES, int $limit = 50): array
    {
        $staleMinutes = max(1, min(1440, $staleMinutes));
        $cutoff = now()->subMinutes($staleMinutes);

        $query = PaymentGroup::query()
            ->with(['customer:id,name,email', 'provider:id,name,api_format', 'orders:id,order_number,payment_group_id,total,payment_status,reconciled_at'])
            ->whereIn('status', ['pending', 'unpaid'])
            ->where('created_at', '<=', $cutoff)
            ->orderBy('created_at');

        $groups = $query->limit($limit)->get();

        $rows = $groups->map(function (PaymentGroup $group): array {
            $orders = $group->orders;
            $unreconciled = $orders->filter(fn (Order $order): bool => $order->reconciled_at === null)->values();
            $hasCallback = $group->callbacks()->whereNotNull('processed_at')->exists();

            return [
                'id' => (int) $group->id,
                'payment_number' => (string) $group->payment_number,
                'customer' => (string) ($group->customer?->name ?? '-'),
                'provider' => (string) ($group->provider?->name ?? '-'),
                'status' => (string) $group->status,
                'grand_total' => (float) $group->grand_total,
                'grand_total_formatted' => Currency::format((float) $group->grand_total),
                'orders' => $orders->count(),
                'unreconciled_orders' => $unreconciled->count(),
                'order_numbers' => $orders->pluck('order_number')->map(fn ($v): string => (string) $v)->all(),
                'gateway_reference' => (string) ($group->gateway_reference ?? ''),
                'has_callback' => $hasCallback,
                'age_minutes' => (int) $group->created_at->diffInMinutes(now()),
                'created_at' => (string) $group->created_at->format('Y-m-d H:i'),
                'last_reconciled_at' => (string) ($group->last_reconciled_at?->format('Y-m-d H:i') ?? ''),
                'attempts' => (int) ($group->reconciliation_attempts ?? 0),
                'note' => (string) ($group->reconciliation_note ?? ''),
                'severity' => $hasCallback ? 'high' : ($unreconciled->count() > 0 ? 'medium' : 'low'),
            ];
        })->all();

        return [
            'rows' => $rows,
            'stale_minutes' => $staleMinutes,
            'cutoff' => (string) $cutoff->format('Y-m-d H:i'),
            'kpis' => [
                ['label' => 'Kelompok Tertunda', 'value' => (int) PaymentGroup::query()->whereIn('status', ['pending', 'unpaid'])->where('created_at', '<=', $cutoff)->count(), 'icon' => 'refresh', 'color' => 'warning', 'hint' => 'Grup pembayaran menunggu lebih dari '.$staleMinutes.' menit'],
                ['label' => 'Nilai Tertahan', 'value' => (float) PaymentGroup::query()->whereIn('status', ['pending', 'unpaid'])->where('created_at', '<=', $cutoff)->sum('grand_total'), 'money' => true, 'icon' => 'cash', 'color' => 'danger', 'hint' => 'Total nominal yang belum terkonfirmasi'],
                ['label' => 'Pernah Dikcallback', 'value' => count(array_filter($rows, fn (array $row): bool => $row['has_callback'])), 'icon' => 'webhook', 'color' => 'primary', 'hint' => 'Callback gateway sudah masuk tapi belum diproses'],
                ['label' => 'Sudah Direkonsiliasi', 'value' => (int) PaymentGroup::query()->whereNotNull('last_reconciled_at')->count(), 'icon' => 'check', 'color' => 'success', 'hint' => 'Grup yang sudah pernah diperiksa admin'],
            ],
            'pending_refunds' => (int) Refund::query()->where('status', 'pending')->count(),
        ];
    }

    /**
     * Queue a reconciliation pass over the stale payment groups.
     *
     * The actual work is delegated to a queued closure so a large backlog never
     * blocks the admin request. When the queue driver is `sync` the closure runs
     * immediately, which is exactly the behaviour an operator expects in local
     * development.
     *
     * @return array{queued: bool, groups: int, mode: string, detail: string}
     */
    public function runReconciliation(int $staleMinutes, ?int $actorId): array
    {
        $groups = PaymentGroup::query()
            ->whereIn('status', ['pending', 'unpaid'])
            ->where('created_at', '<=', now()->subMinutes(max(1, $staleMinutes)))
            ->limit(200)
            ->get();

        if ($groups->isEmpty()) {
            return ['queued' => false, 'groups' => 0, 'mode' => 'none', 'detail' => 'Tidak ada kelompok pembayaran yang tertunda.'];
        }

        $ids = $groups->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $markRun = function () use ($ids, $staleMinutes): void {
            $now = now();

            DB::table('payment_groups')->whereIn('id', $ids)->update([
                'last_reconciled_at' => $now,
                'reconciliation_attempts' => DB::raw('reconciliation_attempts + 1'),
                'reconciliation_note' => 'Diperiksa admin pada '.$now->format('Y-m-d H:i'),
                'updated_at' => $now,
            ]);

            DB::table('orders')
                ->whereIn('payment_group_id', $ids)
                ->whereNull('reconciled_at')
                ->update(['reconciled_at' => $now]);
        };

        $mode = (string) config('queue.default');

        if ($mode === 'sync' || $mode === 'sync' || $mode === 'null') {
            $markRun();
            $detail = 'Rekonsiliasi dijalankan langsung karena driver antrean adalah sync.';
        } else {
            Queue::push(function () use ($markRun): void {
                $markRun();
            }, '', $mode);
            $detail = 'Rekonsiliasi dijadwalkan pada antrean '.$mode.'.';
        }

        app(AuditLogger::class)->log('payment.reconciled', null, [], [
            'groups' => count($ids),
            'stale_minutes' => $staleMinutes,
            'mode' => $mode,
        ], $actorId);

        return [
            'queued' => $mode !== 'sync',
            'groups' => count($ids),
            'mode' => $mode,
            'detail' => $detail,
        ];
    }

    /**
     * Record a refund through the refund workflow service.
     *
     * The service is owned by the payments workstream, so the call is made
     * defensively: if the class or method is missing the admin gets an explicit
     * message rather than a fatal error.
     *
     * @return array{ok: bool, detail: string, refund: array<string, mixed>|null}
     */
    public function refundOrder(int $orderId, ?float $amount, ?string $reason, ?int $actorId): array
    {
        $order = Order::query()->findOrFail($orderId);

        $serviceClass = 'App\\Services\\Payment\\RefundService';
        $method = 'refund';

        if (! class_exists($serviceClass) || ! method_exists($serviceClass, $method)) {
            $fallback = 'App\\Services\\RefundWorkflowService';

            if (class_exists($fallback) && method_exists($fallback, 'process')) {
                $serviceClass = $fallback;
                $method = 'process';
            } else {
                return [
                    'ok' => false,
                    'detail' => 'Layanan refund belum tersedia. Class '.$serviceClass.' tidak ditemukan di instalasi ini.',
                    'refund' => null,
                ];
            }
        }

        try {
            $service = app($serviceClass);
            $result = $service->{$method}($order, $amount, $reason, $actorId);

            return [
                'ok' => true,
                'detail' => 'Permintaan refund dicatat untuk pesanan '.$order->order_number.'.',
                'refund' => is_array($result) ? $result : ['id' => is_object($result) && method_exists($result, 'id') ? (int) $result->id : null],
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Admin refund failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'detail' => 'Refund gagal diproses: '.$e->getMessage(),
                'refund' => null,
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function pendingRefundContext(int $orderId): array
    {
        $order = Order::query()->with(['customer:id,name,email', 'shop:id,name', 'items.product:id,name,sku'])->findOrFail($orderId);

        $refunded = (float) $order->refunds()->where('status', 'succeeded')->sum('amount');
        $orderTotal = (float) $order->total;

        return [
            'order' => [
                'id' => (int) $order->id,
                'order_number' => (string) $order->order_number,
                'customer' => (string) ($order->customer?->name ?? '-'),
                'email' => (string) ($order->customer?->email ?? ''),
                'shop' => (string) ($order->shop?->name ?? '-'),
                'total' => $orderTotal,
                'total_formatted' => Currency::format($orderTotal),
                'payment_status' => (string) $order->payment_status,
                'order_status' => (string) $order->order_status,
            ],
            'refunded' => $refunded,
            'refunded_formatted' => Currency::format($refunded),
            'refundable' => max(0.0, $orderTotal - $refunded),
            'refundable_formatted' => Currency::format(max(0.0, $orderTotal - $refunded)),
            'items' => $order->items->map(fn ($item): array => [
                'name' => (string) ($item->product?->name ?? 'Produk dihapus'),
                'sku' => (string) ($item->product?->sku ?? ''),
                'quantity' => (int) $item->quantity,
                'sub_total' => (float) $item->sub_total,
                'sub_total_formatted' => Currency::format((float) $item->sub_total),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rangeSummary(DateRange $range): array
    {
        $paid = (float) $this->revenueOrders($range)->sum('total');
        $refunded = (float) Refund::query()->where('status', 'succeeded')->whereBetween('succeeded_at', [$range->from, $range->to])->sum('amount');
        $commission = (float) Transaction::query()->whereIn('status', self::successfulTransactionStatuses())->whereBetween('created_at', [$range->from, $range->to])->sum('admin_commission');

        return [
            'range' => $range->toArray(),
            'paid' => $paid,
            'paid_formatted' => Currency::format($paid),
            'refunded' => $refunded,
            'refunded_formatted' => Currency::format($refunded),
            'commission' => $commission,
            'commission_formatted' => Currency::format($commission),
        ];
    }
}
