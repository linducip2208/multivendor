<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The OrderStateMachine legitimately persists intermediate lifecycle states
 * (payment_pending, paid, packed, completed, cancel_requested,
 * return_requested, refund_pending, refunded) that the original narrow
 * `orders.order_status` enum did not allow. On MySQL strict mode those
 * writes failed. Widen the enum to the full stored vocabulary
 * (see App\Enums\OrderStatus::stored()).
 */
return new class extends Migration
{
    private const VALUES = [
        'pending', 'payment_pending', 'paid', 'confirmed', 'processing',
        'packed', 'shipped', 'delivered', 'completed', 'cancel_requested',
        'canceled', 'return_requested', 'returned', 'refund_pending',
        'refunded', 'failed',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('orders')) {
            return;
        }

        $list = implode(',', array_map(fn ($v) => "'{$v}'", self::VALUES));
        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `order_status` ENUM({$list}) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('orders')) {
            return;
        }

        // Rollback keeps only states reachable under the legacy vocabulary.
        DB::statement("UPDATE `orders` SET `order_status` = 'pending' WHERE `order_status` NOT IN ('pending','confirmed','processing','shipped','delivered','canceled','returned','failed')");
        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `order_status` ENUM('pending','confirmed','processing','shipped','delivered','canceled','returned','failed') NOT NULL DEFAULT 'pending'");
    }
};
