<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vat_taxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('rate', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('vat_tax_id')->nullable()->after('tax_type')->constrained('vat_taxes')->nullOnDelete();
        });
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 10);
            $table->string('group', 50);
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['locale', 'group', 'key']);
        });
        Schema::create('delivery_man_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_man_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable();
            $table->timestamps();
        });
        Schema::create('delivery_cash_collects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_man_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->boolean('collected')->default(false);
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('delivery_cash_collects');
        Schema::dropIfExists('delivery_man_earnings');
        Schema::dropIfExists('translations');
        Schema::table('products', fn($t) => $t->dropConstrainedForeignId('vat_tax_id'));
        Schema::dropIfExists('vat_taxes');
    }
};
