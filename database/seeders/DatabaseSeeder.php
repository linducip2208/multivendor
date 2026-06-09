<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            DemoDataSeeder::class,
            MassiveDemoSeeder::class,
            CompleteDemoSeeder::class,
            OrderTransactionSeeder::class,
        ]);
    }
}
