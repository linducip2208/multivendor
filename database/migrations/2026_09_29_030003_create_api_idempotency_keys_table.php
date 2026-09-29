<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('api_idempotency_keys')) {
            return;
        }

        Schema::create('api_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('principal_type', 32);
            $table->string('principal_id', 64);
            $table->string('idempotency_key', 128);
            $table->string('request_fingerprint', 64);
            $table->string('method', 8);
            $table->string('endpoint', 191);
            $table->string('state', 16)->default('pending');
            $table->smallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->json('response_headers')->nullable();
            $table->string('resource_type', 64)->nullable();
            $table->string('resource_id', 64)->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['principal_type', 'principal_id', 'idempotency_key'],
                'api_idempotency_principal_key_unique'
            );
            $table->index('expires_at', 'api_idempotency_expires_at_index');
            $table->index(['principal_type', 'principal_id'], 'api_idempotency_principal_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_idempotency_keys');
    }
};
