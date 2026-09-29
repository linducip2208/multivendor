<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Double-entry ledger.
 *
 * Every financial event writes balanced debit/credit pairs. A transaction that
 * does not balance throws, so money can never silently disappear or appear.
 * All amounts are integer minor units internally (see {@see Money}) and only
 * converted to DECIMAL(15,2) at the storage boundary.
 */
class LedgerService
{
    /**
     * Post a balanced transaction.
     *
     * @param  array<int, array{account: string, direction: 'debit'|'credit', amount: float|int|string, memo?: string|null}>  $lines
     * @param  array<string, mixed>  $context
     */
    public function post(
        string $entryType,
        array $lines,
        array $context = [],
        ?Order $order = null,
    ): string {
        $normalised = $this->normalise($lines);

        if ($normalised === []) {
            throw new RuntimeException("Ledger transaction [{$entryType}] has no lines.");
        }

        $debits = Money::sum(array_map(fn (array $l) => $l['money'], array_filter($normalised, fn (array $l) => $l['direction'] === 'debit')));
        $credits = Money::sum(array_map(fn (array $l) => $l['money'], array_filter($normalised, fn (array $l) => $l['direction'] === 'credit')));

        if ($debits->minor !== $credits->minor) {
            throw new RuntimeException(sprintf(
                'Ledger transaction [%s] is unbalanced: debit %s vs credit %s.',
                $entryType,
                $debits->toDecimal(),
                $credits->toDecimal(),
            ));
        }

        $group = (string) ($context['transaction_group'] ?? Str::uuid()->toString());

        $now = now();

        foreach ($normalised as $line) {
            $account = $this->resolveAccount($line['account']);

            LedgerEntry::create([
                'uuid' => (string) Str::uuid(),
                'transaction_group' => $group,
                'account_id' => $account->id,
                'direction' => $line['direction'],
                'amount' => $line['money']->toDecimal(),
                'currency' => (string) ($context['currency'] ?? 'IDR'),
                'entry_type' => $entryType,
                'reference_type' => $context['reference_type'] ?? null,
                'reference_id' => $context['reference_id'] ?? null,
                'order_id' => $order?->id ?? ($context['order_id'] ?? null),
                'shop_id' => $context['shop_id'] ?? $order?->shop_id,
                'user_id' => $context['user_id'] ?? null,
                'memo' => $line['memo'] ?? ($context['memo'] ?? null),
                'meta' => $context['meta'] ?? null,
                'posted_at' => $now,
            ]);
        }

        return $group;
    }

    /**
     * Settlement posting for a delivered vendor order.
     *
     * The platform has collected money from the customer; at delivery it
     * recognises the split between platform commission, the vendor's payable and
     * the tax liability.
     */
    public function postOrderSettlement(Order $order, array $split): string
    {
        $lines = [
            ['account' => 'platform_revenue', 'direction' => 'credit', 'amount' => $split['commission'], 'memo' => 'Commission '.$order->order_number],
            ['account' => 'vendor_payable', 'direction' => 'credit', 'amount' => $split['vendor_amount'], 'memo' => 'Vendor earning '.$order->order_number],
        ];

        if (($split['tax'] ?? 0) > 0) {
            $lines[] = ['account' => 'tax_payable', 'direction' => 'credit', 'amount' => $split['tax'], 'memo' => 'Tax '.$order->order_number];
        }

        $debit = Money::sum(array_map(fn ($l) => $l['amount'], $lines));
        array_unshift($lines, [
            'account' => 'vendor_advance',
            'direction' => 'debit',
            'amount' => $debit->toFloat(),
            'memo' => 'Recognise revenue '.$order->order_number,
        ]);

        return $this->post('order_settlement', $lines, [
            'reference_type' => Order::class,
            'reference_id' => $order->id,
            'shop_id' => $order->shop_id,
            'order_id' => $order->id,
        ], $order);
    }

    /** Customer pays; the money is held in gateway escrow. */
    public function postPaymentReceived(Order $order, float $amount, ?string $transactionGroup = null): string
    {
        return $this->post('payment_received', [
            ['account' => 'gateway_escrow', 'direction' => 'debit', 'amount' => $amount],
            ['account' => 'customer_receivable', 'direction' => 'credit', 'amount' => $amount],
        ], [
            'reference_type' => Order::class,
            'reference_id' => $order->id,
            'order_id' => $order->id,
            'transaction_group' => $transactionGroup,
            'memo' => 'Payment '.$order->order_number,
        ], $order);
    }

    /** Refund leaves escrow back to the customer's receivable. */
    public function postRefund(Order $order, float $amount, ?string $refundNumber = null): string
    {
        return $this->post('refund', [
            ['account' => 'customer_receivable', 'direction' => 'debit', 'amount' => $amount],
            ['account' => 'gateway_escrow', 'direction' => 'credit', 'amount' => $amount],
        ], [
            'reference_type' => Order::class,
            'reference_id' => $order->id,
            'order_id' => $order->id,
            'memo' => $refundNumber ? 'Refund '.$refundNumber : 'Refund '.$order->order_number,
        ], $order);
    }

    /** Vendor withdraws a settled balance. */
    public function postPayout(int $userId, float $amount, int $shopId, ?string $reference = null): string
    {
        return $this->post('vendor_payout', [
            ['account' => 'vendor_payable', 'direction' => 'debit', 'amount' => $amount],
            ['account' => 'gateway_escrow', 'direction' => 'credit', 'amount' => $amount],
        ], [
            'reference_type' => 'payout',
            'reference_id' => null,
            'shop_id' => $shopId,
            'user_id' => $userId,
            'memo' => $reference,
        ]);
    }

    /** Balance of a single account (debit-positive for assets/expenses). */
    public function balance(string $accountCode): float
    {
        $account = $this->resolveAccount($accountCode);
        $debit = (float) LedgerEntry::where('account_id', $account->id)->where('direction', 'debit')->sum('amount');
        $credit = (float) LedgerEntry::where('account_id', $account->id)->where('direction', 'credit')->sum('amount');

        return $account->isNormalDebit() ? $debit - $credit : $credit - $debit;
    }

    /** @return array<string, float> */
    public function balances(?int $shopId = null): array
    {
        $query = LedgerEntry::query()->selectRaw('ledger_accounts.code, ledger_accounts.type, ledger_accounts.normal_balance, SUM(CASE WHEN ledger_entries.direction = \'debit\' THEN ledger_entries.amount ELSE 0 END) AS debit, SUM(CASE WHEN ledger_entries.direction = \'credit\' THEN ledger_entries.amount ELSE 0 END) AS credit')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.account_id')
            ->groupBy('ledger_accounts.code', 'ledger_accounts.type', 'ledger_accounts.normal_balance');

        if ($shopId) {
            $query->where('ledger_entries.shop_id', $shopId);
        }

        $out = [];
        foreach ($query->get() as $row) {
            $debit = (float) $row->debit;
            $credit = (float) $row->credit;
            $out[$row->code] = $row->normal_balance === 'debit' ? $debit - $credit : $credit - $debit;
        }

        return $out;
    }

    /**
     * Create the platform's chart of accounts. Idempotent.
     */
    public function ensureSystemAccounts(): void
    {
        $definitions = [
            'customer_receivable' => ['Customer Receivable', 'asset', 'debit'],
            'gateway_escrow' => ['Payment Gateway Escrow', 'asset', 'debit'],
            'cash' => ['Cash on Hand', 'asset', 'debit'],
            'vendor_advance' => ['Unapplied Vendor Revenue', 'asset', 'debit'],
            'vendor_payable' => ['Vendor Payable', 'liability', 'credit'],
            'customer_wallet_liability' => ['Customer Wallet Liability', 'liability', 'credit'],
            'platform_revenue' => ['Platform Revenue', 'revenue', 'credit'],
            'commission_revenue' => ['Commission Revenue', 'revenue', 'credit'],
            'shipping_revenue' => ['Shipping Revenue', 'revenue', 'credit'],
            'tax_payable' => ['Tax Payable', 'liability', 'credit'],
            'discount_expense' => ['Discount Expense', 'expense', 'debit'],
            'refund_expense' => ['Refund Expense', 'expense', 'debit'],
            'platform_fee_expense' => ['Platform Fee Expense', 'expense', 'debit'],
        ];

        foreach ($definitions as $code => [$name, $type, $normal]) {
            LedgerAccount::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => $type, 'normal_balance' => $normal, 'is_system' => true],
            );
        }
    }

    private function resolveAccount(string $code): LedgerAccount
    {
        $account = LedgerAccount::where('code', $code)->first();

        if (! $account) {
            $this->ensureSystemAccounts();
            $account = LedgerAccount::where('code', $code)->first();
        }

        if (! $account) {
            throw new RuntimeException("Ledger account [{$code}] does not exist.");
        }

        return $account;
    }

    /**
     * @return list<array{account: string, direction: string, money: Money, memo: ?string}>
     */
    private function normalise(array $lines): array
    {
        $out = [];

        foreach ($lines as $line) {
            $amount = $line['amount'] ?? 0;
            if (! is_numeric($amount) || (float) $amount < 0) {
                continue;
            }
            $out[] = [
                'account' => (string) $line['account'],
                'direction' => $line['direction'] === 'credit' ? 'credit' : 'debit',
                'money' => Money::of($amount),
                'memo' => $line['memo'] ?? null,
            ];
        }

        return $out;
    }
}
