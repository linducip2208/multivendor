<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PayoutStateMachine mempertahankan status antara pending → approved →
 * processing → completed/failed (dengan retry failed → processing) yang
 * tidak muat di enum `vendor_withdraw_requests.status` original
 * (pending/approved/rejected/completed). Tanpa pelebaran ini, transisi ke
 * processing/failed gagal pada MySQL strict mode. Mengikuti pola migrasi
 * 2026_09_29_050000 (hanya MySQL; driver lain no-op).
 */
return new class extends Migration
{
    private const VALUES = [
        'pending', 'approved', 'processing', 'completed', 'failed', 'rejected',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('vendor_withdraw_requests')) {
            return;
        }

        $list = implode(',', array_map(fn ($v) => "'{$v}'", self::VALUES));
        DB::statement("ALTER TABLE `vendor_withdraw_requests` MODIFY COLUMN `status` ENUM({$list}) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('vendor_withdraw_requests')) {
            return;
        }

        DB::statement("UPDATE `vendor_withdraw_requests` SET `status` = 'pending' WHERE `status` NOT IN ('pending','approved','rejected','completed')");
        DB::statement("ALTER TABLE `vendor_withdraw_requests` MODIFY COLUMN `status` ENUM('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending'");
    }
};
