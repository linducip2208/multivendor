<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'balance', 'pending_balance'])]
class Wallet extends Model
{
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'pending_balance' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function credit(float $amount, string $description = null, string $referenceType = null, int $referenceId = null): WalletTransaction
    {
        $before = $this->balance;
        $this->balance += $amount;
        $this->save();

        return WalletTransaction::create([
            'wallet_id' => $this->id,
            'amount' => $amount,
            'type' => 'credit',
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'balance_before' => $before,
            'balance_after' => $this->balance,
            'status' => 'completed',
        ]);
    }

    public function debit(float $amount, string $description = null, string $referenceType = null, int $referenceId = null): WalletTransaction
    {
        $before = $this->balance;
        $this->balance -= $amount;
        $this->save();

        return WalletTransaction::create([
            'wallet_id' => $this->id,
            'amount' => $amount,
            'type' => 'debit',
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'balance_before' => $before,
            'balance_after' => $this->balance,
            'status' => 'completed',
        ]);
    }
}
