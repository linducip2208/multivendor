<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('api_keys')) {
            if (! Schema::hasColumn('api_keys', 'user_id')) {
                Schema::table('api_keys', function (Blueprint $table): void {
                    $table->bigInteger('user_id')->nullable();
                    $table->string('label', 191)->nullable();
                    $table->string('last_used_ip', 45)->nullable();
                    $table->string('revoked_reason', 191)->nullable();
                });
            }

            if (! Schema::hasIndex('api_keys', ['user_id'])) {
                Schema::table('api_keys', function (Blueprint $table): void {
                    $table->index('user_id', 'api_keys_user_id_index');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('api_keys') && Schema::hasColumn('api_keys', 'user_id')) {
            Schema::table('api_keys', function (Blueprint $table): void {
                $table->dropIndex('api_keys_user_id_index');
                $table->dropColumn(['user_id', 'label', 'last_used_ip', 'revoked_reason']);
            });
        }
    }
};
