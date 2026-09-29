<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Refund;
use App\Models\VendorWithdrawRequest;
use App\Services\Finance\LedgerService;
use Illuminate\Support\Facades\DB;

/**
 * Money reporting built on the double-entry ledger where one exists, and on
 * recorded transactions where the ledger has not been wired up yet.
 */
final class FinanceAnalyticsService extends AnalyticsService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * @return array<string, mixed>
     */
    public function report(DateRange $range, int $perPage = 20, int $page = 1, string $account = '', string $search = ''): array
    {
        return $this->remember('finance', $range, function () use ($range, $perPage, $page, $account, $search): array {
            $paid = (float) $this->revenueOrders($range)->sum('total');
            $refunded = (float) $this->refundTotal($range);
            $commission = $this->commissionTotal($range);
            $vendorShare = $this->vendorShareTotal($range);
            $tax = (float) $this->revenueOrders($range)->sum('tax');
            $shipping = (float) $this->revenueOrders($range)->sum('shipping_cost');
            $discount = (float) $this->revenueOrders($range)->sum('discount')
                + (float) $this->revenueOrders($range)->sum('coupon_discount');

            $payouts = VendorWithdrawRequest::query()
                ->whereIn('status', ['approved', 'completed'])
                ->whereBetween('created_at', [$range->from, $range->to]);

            return [
                'range' => $range->toArray(),
                'kpis' => [
                    'gross' => ['value' => $paid, 'label' => 'Nilai Pembayaran', 'money' => true, 'trend' => $this->delta(0.0, 0.0)],
                    'refunds' => ['value' => $refunded, 'label' => 'Refund', 'money' => true, 'trend' => $this->delta(0.0, 0.0)],
                    'net' => ['value' => $paid - $refunded, 'label' => 'Bersih', 'money' => true, 'trend' => $this->delta(0.0, 0.0)],
                    'commission' => ['value' => $commission, 'label' => 'Komisi Platform', 'money' => true, 'trend' => $this->delta(0.0, 0.0)],
                    'vendor' => ['value' => $vendorShare, 'label' => 'Hak Vendor', 'money' => true, 'trend' => $this->delta(0.0, 0.0)],
                    'tax' => ['value' => $tax, 'label' => 'Pajak Terkumpul', 'money' => true, 'trend' => $this->delta(0.0, 0.0)],
                    'shipping' => ['value' => $shipping, 'label' => 'Pendapatan Ongkir', 'money' => true, 'trend' => $this->delta(0.0, 0.0)],
                    'discount' => ['value' => $discount, 'label' => 'Diskon Diberikan', 'money' => true, 'trend' => $this->delta(0.0, 0.0)],
                    'payouts' => [
                        'value' => (float) (clone $payouts)->sum('amount'),
                        'label' => 'Payout Diproses',
                        'money' => true,
                        'trend' => $this->delta(0.0, 0.0),
                    ],
                    'payout_queue' => [
                        'value' => (int) VendorWithdrawRequest::query()->where('status', 'pending')->count(),
                        'label' => 'Payout Menunggu',
                        'money' => false,
                        'trend' => $this->delta(0.0, 0.0),
                    ],
                ],
                'flow' => $this->flow($range),
                'accounts' => $this->accountBalances(),
                'entries' => $this->entries($range, $perPage, $page, $account, $search),
            ];
        }, ['per_page' => $perPage, 'page' => $page, 'account' => $account, 'search' => $search]);
    }

    /**
     * @return array{labels: list<string>, inflow: list<float>, refund: list<float>, commission: list<float>}
     */
    private function flow(DateRange $range): array
    {
        $expression = match (DB::connection()->getDriverName()) {
            'sqlite' => 'date(created_at)',
            'pgsql' => "to_char(created_at, 'YYYY-MM-DD')",
            default => 'DATE(created_at)',
        };

        $labels = $range->labels();

        $payments = DB::table('orders')
            ->whereIn('order_status', self::revenueOrderStatuses())
            ->whereIn('payment_status', self::paidPaymentStatuses())
            ->whereBetween('created_at', [$range->from, $range->to])
            ->selectRaw($expression.' as bucket, SUM(total) as amount')
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        $refunds = $this->has('refunds')
            ? DB::table('refunds')
                ->where('status', 'succeeded')
                ->whereBetween('succeeded_at', [$range->from, $range->to])
                ->selectRaw($expression.' as bucket, SUM(amount) as amount')
                ->groupBy('bucket')
                ->get()
                ->keyBy('bucket')
            : collect();

        $commissions = $this->has('transactions')
            ? DB::table('transactions')
                ->whereIn('status', self::successfulTransactionStatuses())
                ->whereBetween('created_at', [$range->from, $range->to])
                ->selectRaw($expression.' as bucket, SUM(admin_commission) as amount')
                ->groupBy('bucket')
                ->get()
                ->keyBy('bucket')
            : collect();

        return [
            'labels' => $labels,
            'inflow' => $this->densify($labels, $payments->all(), 'amount'),
            'refund' => $this->densify($labels, $refunds->all(), 'amount'),
            'commission' => $this->densify($labels, $commissions->all(), 'amount'),
        ];
    }

    /**
     * @return list<array{code: string, name: string, type: string, balance: float, normal_balance: string, debit: float, credit: float}>
     */
    public function accountBalances(): array
    {
        if (! $this->has('ledger_accounts')) {
            return [];
        }

        $rows = LedgerEntry::query()
            ->select('account_id')
            ->selectRaw("SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END) as debit")
            ->selectRaw("SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END) as credit")
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        return LedgerAccount::query()->orderBy('type')->orderBy('code')->get()
            ->map(function (LedgerAccount $account) use ($rows): array {
                $row = $rows[$account->id] ?? null;
                $debit = (float) ($row->debit ?? 0);
                $credit = (float) ($row->credit ?? 0);

                return [
                    'code' => (string) $account->code,
                    'name' => (string) $account->name,
                    'type' => (string) $account->type,
                    'normal_balance' => (string) $account->normal_balance,
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => $account->normal_balance === 'debit' ? $debit - $credit : $credit - $debit,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function entries(DateRange $range, int $perPage, int $page, string $account, string $search): array
    {
        if (! $this->has('ledger_entries')) {
            return ['rows' => [], 'total' => 0, 'per_page' => $perPage, 'current_page' => $page, 'last_page' => 1];
        }

        $query = LedgerEntry::query()
            ->with('account:id,code,name,type,normal_balance')
            ->whereBetween('posted_at', [$range->from, $range->to]);

        if ($account !== '') {
            $query->whereHas('account', fn ($q) => $q->where('code', $account));
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('memo', 'like', '%'.$search.'%')
                    ->orWhere('transaction_group', 'like', '%'.$search.'%')
                    ->orWhere('entry_type', 'like', '%'.$search.'%');
            });
        }

        $perPage = max(5, min(100, $perPage));
        $page = max(1, $page);
        $total = (clone $query)->count();

        $rows = $query->orderByDesc('posted_at')
            ->orderBy('id')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (LedgerEntry $entry): array => [
                'id' => (int) $entry->id,
                'group' => (string) $entry->transaction_group,
                'account_code' => (string) ($entry->account?->code ?? ''),
                'account_name' => (string) ($entry->account?->name ?? ''),
                'account_type' => (string) ($entry->account?->type ?? ''),
                'direction' => (string) $entry->direction,
                'amount' => (float) $entry->amount,
                'entry_type' => (string) $entry->entry_type,
                'memo' => (string) ($entry->memo ?? ''),
                'posted_at' => (string) ($entry->posted_at?->format('Y-m-d H:i') ?? ''),
            ])
            ->all();

        return [
            'rows' => $rows,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function refundTotal(DateRange $range): float
    {
        if (! $this->has('refunds')) {
            return 0.0;
        }

        return (float) Refund::query()
            ->where('status', 'succeeded')
            ->whereBetween('succeeded_at', [$range->from, $range->to])
            ->sum('amount');
    }

    public function commissionTotal(DateRange $range): float
    {
        if (! $this->has('transactions')) {
            return 0.0;
        }

        return (float) DB::table('transactions')
            ->whereIn('status', self::successfulTransactionStatuses())
            ->whereBetween('created_at', [$range->from, $range->to])
            ->sum('admin_commission');
    }

    public function vendorShareTotal(DateRange $range): float
    {
        if (! $this->has('transactions')) {
            return 0.0;
        }

        return (float) DB::table('transactions')
            ->whereIn('status', self::successfulTransactionStatuses())
            ->whereBetween('created_at', [$range->from, $range->to])
            ->sum('vendor_amount');
    }

    /**
     * @return array<string, float>
     */
    public function ledgerBalances(?int $shopId = null): array
    {
        try {
            return $this->ledger->balances($shopId);
        } catch (\Throwable) {
            return [];
        }
    }
}
