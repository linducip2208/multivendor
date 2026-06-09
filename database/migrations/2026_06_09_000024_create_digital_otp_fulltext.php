<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('digital_product_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->string('otp', 10);
            $table->boolean('verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
        Schema::table('products', function (Blueprint $table) {
            if (config('database.default') !== 'sqlite') {
                $table->fullText(['name', 'short_description', 'description']);
            }
        });
    }
    public function down(): void { Schema::dropIfExists('digital_product_otps'); Schema::table('products', fn($t)=>$t->dropFullText(['name','short_description','description'])); }
};
