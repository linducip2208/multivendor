<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Default financial settings.
 *
 * Every value is written with an "insert if absent" guard so an operator who has
 * already tuned a setting keeps their value and re-running the migration is a
 * no-op.
 */
return new class extends Migration
{
    private const DEFAULTS = [
        'payment_amount_tolerance' => ['0', 'number'],
        'payment_reconcile_after_minutes' => ['15', 'number'],
        'payment_expiry_minutes' => ['1440', 'number'],
        'loyalty_earn_rate' => ['1000', 'number'],
        'delivery_earning_rate' => ['80', 'number'],
    ];

    public function up(): void
    {
        foreach (self::DEFAULTS as $key => [$value, $type]) {
            $exists = DB::table('system_settings')->where('key', $key)->exists();

            if (! $exists) {
                DB::table('system_settings')->insert([
                    'key' => $key,
                    'value' => $value,
                    'type' => $type,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('key', array_keys(self::DEFAULTS))->delete();
    }
};
