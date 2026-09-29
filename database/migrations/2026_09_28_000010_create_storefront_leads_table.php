<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('storefront_leads')) {
            return;
        }

        Schema::create('storefront_leads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 180)->index();
            $table->string('phone', 32)->nullable();
            $table->string('company', 160)->nullable();
            $table->text('message');
            $table->string('source', 80)->default('landing')->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->boolean('is_handled')->default(false)->index();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['source', 'is_handled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_leads');
    }
};
