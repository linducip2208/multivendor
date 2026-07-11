<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('shops', 'onboarding_completed')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->boolean('onboarding_completed')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shops', 'onboarding_completed')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->dropColumn('onboarding_completed');
            });
        }
    }
};
