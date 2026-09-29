<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'type', 'normal_balance', 'tenant_id', 'is_system'])]
class LedgerAccount extends Model
{
    protected $table = 'ledger_accounts';

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'tenant_id' => 'integer',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'account_id');
    }

    public function isNormalDebit(): bool
    {
        return $this->normal_balance === 'debit';
    }

    public static function systemCodes(): array
    {
        return [
            'customer_receivable', 'gateway_escrow', 'cash',
            'vendor_payable', 'vendor_advance',             'customer_wallet_liability',
            'platform_revenue', 'commission_revenue', 'shipping_revenue', 'tax_payable', 'tax_receivable',
            'settlement_clearing',
            'discount_expense', 'refund_expense', 'platform_fee_expense',
        ];
    }
}
