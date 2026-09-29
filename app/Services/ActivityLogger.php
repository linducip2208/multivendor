<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CustomerActivity;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Lightweight customer activity trail feeding the CRM timeline.
 * Failures are swallowed: telemetry must never break a business transaction.
 */
class ActivityLogger
{
    public static function log(User $user, string $type, array $properties = []): void
    {
        try {
            CustomerActivity::create([
                'customer_id' => $user->id,
                'type' => $type,
                'description' => self::describe($type, $properties),
                'properties' => $properties ?: null,
                'ip_address' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::debug('Activity log failed', ['type' => $type, 'error' => $e->getMessage()]);
        }
    }

    private static function describe(string $type, array $properties): string
    {
        return match ($type) {
            'customer.registered' => 'Mendaftarkan akun baru',
            'order.placed' => 'Membuat pesanan',
            'order.cancelled' => 'Membatalkan pesanan',
            'payment.paid' => 'Melakukan pembayaran',
            'wishlist.added' => 'Menambahkan produk ke favorit',
            'review.created' => 'Menulis ulasan',
            'cart.abandoned' => 'Menninggalkan keranjang',
            'login' => 'Masuk ke akun',
            default => str_replace(['_', '.'], ' ', $type),
        };
    }
}
