<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyTransaction extends Model
{
    protected $fillable = ['customer_id', 'points', 'type', 'description', 'reference_type', 'reference_id'];

    public const REF_CHECKIN = 'checkin';

    public const REF_MISI_PREFIX = 'misi_harian:';

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** Cek klaim misi/check-in pada tanggal tertentu (idempotensi harian, tanpa kolom baru). */
    public static function claimedMission(int $customerId, string $key, ?\DateTimeInterface $date = null): bool
    {
        $ref = $key === 'checkin' ? self::REF_CHECKIN : self::REF_MISI_PREFIX.$key;
        $dayId = (int) ($date ?? now())->format('Ymd');

        return static::where('customer_id', $customerId)
            ->where('reference_type', $ref)
            ->where('reference_id', $dayId)
            ->exists();
    }

    /** @param \Illuminate\Database\Eloquent\Builder<LoyaltyTransaction> $query */
    public function scopeMission($query, string $key)
    {
        $ref = $key === 'checkin' ? self::REF_CHECKIN : self::REF_MISI_PREFIX.$key;

        return $query->where('reference_type', $ref);
    }

    /** @param \Illuminate\Database\Eloquent\Builder<LoyaltyTransaction> $query */
    public function scopeCheckin($query)
    {
        return $query->where('reference_type', self::REF_CHECKIN);
    }
}
