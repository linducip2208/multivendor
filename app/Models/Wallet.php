<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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

    public function credit(float $amount, string $description = null, string $referenceType = null, int $referenceId = null, ?string $referenceKey = null): WalletTransaction
    {
        return $this->mutate('credit', $amount, $description, $referenceType, $referenceId, $referenceKey);
    }

    public function debit(float $amount, string $description = null, string $referenceType = null, int $referenceId = null, ?string $referenceKey = null): WalletTransaction
    {
        return $this->mutate('debit', $amount, $description, $referenceType, $referenceId, $referenceKey);
    }

    public function reserve(float $amount, string $description = null, string $referenceType = null, int $referenceId = null, ?string $referenceKey = null): WalletTransaction
    {
        return $this->mutate('debit', $amount, $description, $referenceType, $referenceId, $referenceKey, 'hold', true);
    }

    public function release(float $amount, string $description = null, string $referenceType = null, int $referenceId = null, ?string $referenceKey = null): WalletTransaction
    {
        return $this->mutate('credit', $amount, $description, $referenceType, $referenceId, $referenceKey, 'release', true);
    }

    /**
     * Tahan saldo (held) tanpa mengubah saldo tersedia.
     *
     * Memakai kolom pending_balance existing sebagai saldo tertahan.
     * Idempoten via reference_key. Riwayat tercatat dengan operation=hold.
     */
    public function holdPending(float $amount, ?string $description = null, ?string $referenceType = null, ?int $referenceId = null, ?string $referenceKey = null): WalletTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Nominal tahan harus lebih dari nol.');
        }

        return DB::transaction(function () use ($amount, $description, $referenceType, $referenceId, $referenceKey) {
            $wallet = static::lockForUpdate()->findOrFail($this->id);
            if ($referenceKey && ($existing = $wallet->transactions()->where('reference_key', $referenceKey)->first())) {
                return $existing;
            }

            $before = (float) $wallet->balance;
            $wallet->pending_balance = (float) $wallet->pending_balance + $amount;
            $wallet->save();

            return $wallet->transactions()->create([
                'amount' => $amount,
                'type' => 'credit',
                'operation' => 'hold',
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_key' => $referenceKey,
                'balance_before' => $before,
                'balance_after' => $before,
                'status' => 'completed',
            ]);
        });
    }

    /**
     * Rilis saldo tertahan menjadi saldo tersedia (saat order completed).
     *
     * Idempoten via reference_key. Riwayat tercatat dengan operation=release.
     */
    public function releaseHeld(float $amount, ?string $description = null, ?string $referenceType = null, ?int $referenceId = null, ?string $referenceKey = null): WalletTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Nominal rilis harus lebih dari nol.');
        }

        return DB::transaction(function () use ($amount, $description, $referenceType, $referenceId, $referenceKey) {
            $wallet = static::lockForUpdate()->findOrFail($this->id);
            if ($referenceKey && ($existing = $wallet->transactions()->where('reference_key', $referenceKey)->first())) {
                return $existing;
            }
            if ((float) $wallet->pending_balance < $amount) {
                throw new \DomainException('Saldo tertahan tidak cukup untuk dirilis.');
            }

            $before = (float) $wallet->balance;
            $wallet->balance = $before + $amount;
            $wallet->pending_balance = (float) $wallet->pending_balance - $amount;
            $wallet->save();

            return $wallet->transactions()->create([
                'amount' => $amount,
                'type' => 'credit',
                'operation' => 'release',
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_key' => $referenceKey,
                'balance_before' => $before,
                'balance_after' => $wallet->balance,
                'status' => 'completed',
            ]);
        });
    }

    /** Ringkasan pisah saldo tersedia vs tertahan. */
    public function heldVsAvailable(): array
    {
        $fresh = static::find($this->id) ?? $this;

        return [
            'available' => (float) $fresh->balance,
            'held' => (float) $fresh->pending_balance,
            'total' => (float) $fresh->balance + (float) $fresh->pending_balance,
        ];
    }

    private function mutate(string $type, float $amount, ?string $description, ?string $referenceType, ?int $referenceId, ?string $referenceKey, ?string $operation = null, bool $movePending = false): WalletTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Nominal wallet harus lebih dari nol.');
        }

        return DB::transaction(function () use ($type, $amount, $description, $referenceType, $referenceId, $referenceKey, $operation, $movePending) {
            $wallet = static::lockForUpdate()->findOrFail($this->id);
            if ($referenceKey && ($existing = $wallet->transactions()->where('reference_key', $referenceKey)->first())) {
                return $existing;
            }
            if ($type === 'debit' && (float) $wallet->balance < $amount) {
                throw new \DomainException('Saldo wallet tidak cukup.');
            }

            $before = (float) $wallet->balance;
            $wallet->balance = $type === 'credit' ? $before + $amount : $before - $amount;
            if ($movePending) {
                $wallet->pending_balance = $operation === 'hold'
                    ? (float) $wallet->pending_balance + $amount
                    : max(0, (float) $wallet->pending_balance - $amount);
            }
            $wallet->save();

            return $wallet->transactions()->create([
                'amount' => $amount,
                'type' => $type,
                'operation' => $operation ?? $type,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_key' => $referenceKey,
                'balance_before' => $before,
                'balance_after' => $wallet->balance,
                'status' => 'completed',
            ]);
        });
    }
}
