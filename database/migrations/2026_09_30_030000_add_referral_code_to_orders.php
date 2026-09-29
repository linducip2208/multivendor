<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kode referral afiliasi per order. Sebelumnya kode dititipkan di
 * orders.coupon_code sehingga berpotensi menimpa kupon diskon yang
 * dipakai bersamaan. Kolom khusus nullable + backward-compatible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'referral_code')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('referral_code', 50)->nullable()->after('coupon_code')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'referral_code')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['referral_code']);
            $table->dropColumn('referral_code');
        });
    }
};
