<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['affiliate_id', 'customer_id', 'landing_path', 'ip_address', 'user_agent', 'converted_order_id', 'converted_at'])]
class AffiliateClick extends Model
{
    protected function casts(): array
    {
        return [
            'converted_at' => 'datetime',
        ];
    }

    public function isConverted(): bool
    {
        return $this->converted_order_id !== null;
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    /** Catat klik afiliasi (dipakai dasbor + tracking link). */
    public static function track(Affiliate $affiliate, array $attrs = []): static
    {
        return static::create([
            'affiliate_id' => $affiliate->id,
            'customer_id' => $attrs['customer_id'] ?? null,
            'landing_path' => isset($attrs['landing_path']) ? mb_substr((string) $attrs['landing_path'], 0, 400) : null,
            'ip_address' => isset($attrs['ip_address']) ? mb_substr((string) $attrs['ip_address'], 0, 45) : null,
            'user_agent' => isset($attrs['user_agent']) ? mb_substr((string) $attrs['user_agent'], 0, 400) : null,
        ]);
    }

    /** Tandai klik berubah menjadi pesanan (idempoten per order). */
    public function markConverted(Order $order): bool
    {
        if ($this->converted_order_id !== null) {
            return false;
        }
        $this->forceFill(['converted_order_id' => $order->id, 'converted_at' => now()])->save();

        return true;
    }
}
