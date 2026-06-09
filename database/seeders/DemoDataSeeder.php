<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Shop;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(['email' => 'admin@multivendor.test'], [
            'name' => 'Admin MultiVendor',
            'password' => 'password',
            'phone' => '081234567890',
            'role' => 'admin',
            'status' => 'active',
        ]);
        Wallet::firstOrCreate(['user_id' => $admin->id], ['balance' => 0]);

        $vendor = User::firstOrCreate(['email' => 'vendor@multivendor.test'], [
            'name' => 'Toko Elektronik Jaya',
            'password' => 'password',
            'phone' => '081234567891',
            'role' => 'vendor',
            'status' => 'active',
        ]);
        Wallet::firstOrCreate(['user_id' => $vendor->id], ['balance' => 500000]);

        Shop::firstOrCreate(['vendor_id' => $vendor->id], [
            'name' => 'Toko Elektronik Jaya',
            'slug' => 'toko-elektronik-jaya',
            'description' => 'Toko elektronik terpercaya dengan produk berkualitas.',
            'address' => 'Jl. Raya No. 123, Jakarta',
            'phone' => '081234567891',
            'commission_type' => 'percentage',
            'commission_value' => 5.00,
            'status' => 'active',
        ]);

        $customer = User::firstOrCreate(['email' => 'customer@multivendor.test'], [
            'name' => 'Budi Santoso',
            'password' => 'password',
            'phone' => '081234567892',
            'role' => 'customer',
            'status' => 'active',
        ]);
        Wallet::firstOrCreate(['user_id' => $customer->id], ['balance' => 1000000]);

        $categories = [
            ['name' => 'Elektronik', 'slug' => 'elektronik', 'icon' => 'fa-laptop', 'status' => true],
            ['name' => 'Fashion', 'slug' => 'fashion', 'icon' => 'fa-tshirt', 'status' => true],
            ['name' => 'Rumah Tangga', 'slug' => 'rumah-tangga', 'icon' => 'fa-home', 'status' => true],
            ['name' => 'Olahraga', 'slug' => 'olahraga', 'icon' => 'fa-futbol', 'status' => true],
            ['name' => 'Buku', 'slug' => 'buku', 'icon' => 'fa-book', 'status' => true],
        ];

        foreach ($categories as $cat) {
            $parent = Category::create($cat);

            if ($cat['slug'] === 'elektronik') {
                Category::create(['parent_id' => $parent->id, 'name' => 'Smartphone', 'slug' => 'smartphone', 'status' => true]);
                Category::create(['parent_id' => $parent->id, 'name' => 'Laptop', 'slug' => 'laptop', 'status' => true]);
            } elseif ($cat['slug'] === 'fashion') {
                Category::create(['parent_id' => $parent->id, 'name' => 'Pria', 'slug' => 'fashion-pria', 'status' => true]);
                Category::create(['parent_id' => $parent->id, 'name' => 'Wanita', 'slug' => 'fashion-wanita', 'status' => true]);
            }
        }
    }
}
