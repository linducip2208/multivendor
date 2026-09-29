<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('api_keys')) {
            Schema::create('api_keys', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('prefix', 16)->index();
                $table->string('key_hash', 128)->unique();
                $table->json('scopes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index('created_by');
                $table->index('tenant_id');
            });
        }

        if (Schema::hasTable('webhook_deliveries') && ! Schema::hasColumn('webhook_deliveries', 'request_headers')) {
            Schema::table('webhook_deliveries', function (Blueprint $table) {
                $table->json('request_headers')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('webhook_deliveries') && Schema::hasColumn('webhook_deliveries', 'request_headers')) {
            Schema::table('webhook_deliveries', function (Blueprint $table) {
                $table->dropColumn('request_headers');
            });
        }

        Schema::dropIfExists('api_keys');
    }
};
