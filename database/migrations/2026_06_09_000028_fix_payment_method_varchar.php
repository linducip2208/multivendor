<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE transactions MODIFY COLUMN payment_method VARCHAR(50) NOT NULL DEFAULT 'transfer'");
            DB::statement("ALTER TABLE orders MODIFY COLUMN payment_method VARCHAR(50) NULL");
        }
    }
    public function down(): void
    {
        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE transactions MODIFY COLUMN payment_method ENUM('midtrans','xendit','transfer','cod','wallet') NOT NULL DEFAULT 'transfer'");
        }
    }
};
