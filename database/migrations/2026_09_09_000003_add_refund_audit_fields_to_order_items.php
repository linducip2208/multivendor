<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->text('refund_admin_note')->nullable()->after('refund_reason');
            $table->timestamp('refund_requested_at')->nullable()->after('refund_admin_note');
            $table->timestamp('refund_decided_at')->nullable()->after('refund_requested_at');
            $table->timestamp('refund_processed_at')->nullable()->after('refund_decided_at');
            $table->index(['refund_status', 'refund_requested_at'], 'order_items_refund_status_requested_index');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_refund_status_requested_index');
            $table->dropColumn(['refund_admin_note', 'refund_requested_at', 'refund_decided_at', 'refund_processed_at']);
        });
    }
};
