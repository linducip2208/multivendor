<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->longText('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->json('images')->nullable();
            $table->string('unit')->nullable();
            $table->integer('min_qty')->default(1);
            $table->integer('max_qty')->default(10);
            $table->unsignedInteger('current_stock')->default(0);
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->enum('product_type', ['physical', 'digital'])->default('physical');
            $table->boolean('refundable')->default(true);
            $table->boolean('featured')->default(false);
            $table->boolean('published')->default(false);
            $table->enum('created_by', ['admin', 'vendor'])->default('vendor');
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('special_price', 15, 2)->nullable();
            $table->enum('discount_type', ['flat', 'percentage'])->nullable();
            $table->dateTime('discount_start')->nullable();
            $table->dateTime('discount_end')->nullable();
            $table->decimal('tax', 5, 2)->default(0);
            $table->enum('tax_type', ['inclusive', 'exclusive'])->default('inclusive');
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->enum('shipping_cost_type', ['flat', 'free', 'category_wise'])->default('flat');
            $table->boolean('multiply_qty')->default(false);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_image')->nullable();
            $table->string('video_url')->nullable();
            $table->string('digital_file')->nullable();
            $table->integer('request_status')->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->enum('status', ['pending', 'approved', 'suspended'])->default('pending');
            $table->timestamps();

            $table->index(['shop_id', 'status']);
            $table->index(['category_id', 'status']);
            $table->index(['featured', 'published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
