<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_subscriptions', function (Blueprint $table) {
            $table->dateTime('starts_at')->change();
            $table->dateTime('ends_at')->nullable()->change();
            $table->dateTime('canceled_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_subscriptions', function (Blueprint $table) {
            $table->timestamp('starts_at')->change();
            $table->timestamp('ends_at')->nullable()->change();
            $table->timestamp('canceled_at')->nullable()->change();
        });
    }
};
