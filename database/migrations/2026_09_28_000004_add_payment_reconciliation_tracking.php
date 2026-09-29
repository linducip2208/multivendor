<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciliation bookkeeping.
 *
 * The gateway can report a payment as settled while the local payment group is
 * still `pending`. The admin reconciliation screen needs to know when a group
 * was last inspected and how many times the probe ran, so an operator can tell
 * "never checked" apart from "checked three times and still nothing".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_groups') && ! Schema::hasColumn('payment_groups', 'last_reconciled_at')) {
            Schema::table('payment_groups', function (Blueprint $table) {
                $table->timestamp('last_reconciled_at')->nullable();
                $table->unsignedInteger('reconciliation_attempts')->default(0);
                $table->text('reconciliation_note')->nullable();
            });
        }

        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'reconciled_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('reconciled_at')->nullable();
            });
        }

        if (Schema::hasTable('campaigns') && ! Schema::hasColumn('campaigns', 'activated_at')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->timestamp('activated_at')->nullable();
                $table->unsignedBigInteger('activated_by')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('campaigns') && Schema::hasColumn('campaigns', 'activated_by')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->dropColumn(['activated_at', 'activated_by']);
            });
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'reconciled_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('reconciled_at');
            });
        }

        if (Schema::hasTable('payment_groups') && Schema::hasColumn('payment_groups', 'last_reconciled_at')) {
            Schema::table('payment_groups', function (Blueprint $table) {
                $table->dropColumn(['last_reconciled_at', 'reconciliation_attempts', 'reconciliation_note']);
            });
        }
    }
};
