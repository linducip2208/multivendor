<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flash_deals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->string('banner')->nullable();
            $table->boolean('status')->default(false);
            $table->boolean('featured')->default(false);
            $table->timestamps();
        });

        Schema::create('flash_deal_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flash_deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('discount_type', ['flat', 'percentage'])->default('percentage');
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('deals_of_the_day', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('discount_type', ['flat', 'percentage'])->default('percentage');
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->date('date');
            $table->timestamps();

            $table->unique(['product_id', 'date']);
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('image');
            $table->string('link')->nullable();
            $table->enum('position', ['hero', 'sidebar', 'footer', 'popup'])->default('hero');
            $table->integer('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
        Schema::dropIfExists('deals_of_the_day');
        Schema::dropIfExists('flash_deal_products');
        Schema::dropIfExists('flash_deals');
    }
};
