<?php

namespace Tests\Unit;

use App\Models\Coupon;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_percentage_coupon_calculates_correctly(): void
    {
        $coupon = Coupon::create([
            'code' => 'HEMAT50', 'coupon_type' => 'percentage',
            'discount_value' => 50, 'min_purchase' => 10000,
            'max_discount' => 50000, 'status' => true,
        ]);

        $discount = $coupon->calculateDiscount(200000);
        $this->assertEquals(50000, $discount);
    }

    public function test_percentage_coupon_respects_max_discount(): void
    {
        $coupon = Coupon::create([
            'code' => 'HEMAT20', 'coupon_type' => 'percentage',
            'discount_value' => 20, 'min_purchase' => 5000,
            'max_discount' => 10000, 'status' => true,
        ]);

        $discount = $coupon->calculateDiscount(100000);
        $this->assertEquals(10000, $discount);
    }

    public function test_fixed_coupon_calculates_correctly(): void
    {
        $coupon = Coupon::create([
            'code' => 'FLAT50K', 'coupon_type' => 'fixed',
            'discount_value' => 50000, 'min_purchase' => 100000,
            'status' => true,
        ]);

        $discount = $coupon->calculateDiscount(150000);
        $this->assertEquals(50000, $discount);
    }

    public function test_coupon_below_min_purchase_returns_zero(): void
    {
        $coupon = Coupon::create([
            'code' => 'MIN100K', 'coupon_type' => 'fixed',
            'discount_value' => 50000, 'min_purchase' => 100000,
            'status' => true,
        ]);

        $discount = $coupon->calculateDiscount(50000);
        $this->assertEquals(0, $discount);
    }

    public function test_inactive_coupon_is_not_valid(): void
    {
        $coupon = Coupon::create([
            'code' => 'OFFLINE', 'coupon_type' => 'percentage',
            'discount_value' => 10, 'status' => false,
        ]);

        $this->assertFalse($coupon->isValid());
    }

    public function test_expired_coupon_is_not_valid(): void
    {
        $coupon = Coupon::create([
            'code' => 'EXPIRED', 'coupon_type' => 'percentage',
            'discount_value' => 10, 'status' => true,
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDay(),
        ]);

        $this->assertFalse($coupon->isValid());
    }

    public function test_coupon_over_limit_is_not_valid(): void
    {
        $coupon = Coupon::create([
            'code' => 'LIMITED', 'coupon_type' => 'percentage',
            'discount_value' => 10, 'usage_limit' => 5,
            'usage_count' => 5, 'status' => true,
        ]);

        $this->assertFalse($coupon->isValid());
    }
}
