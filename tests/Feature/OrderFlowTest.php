<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $vendor;
    protected Shop $shop;
    protected Product $product;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = User::create([
            'name' => 'Customer', 'email' => 'customer@test.com',
            'password' => Hash::make('password'), 'role' => 'customer', 'status' => 'active',
        ]);
        Wallet::create(['user_id' => $this->customer->id, 'balance' => 1000000]);

        $this->vendor = User::create([
            'name' => 'Vendor', 'email' => 'vendor@test.com',
            'password' => Hash::make('password'), 'role' => 'vendor', 'status' => 'active',
        ]);
        Wallet::create(['user_id' => $this->vendor->id, 'balance' => 0]);

        $this->shop = Shop::create([
            'vendor_id' => $this->vendor->id, 'name' => 'Test Shop',
            'slug' => 'test-shop', 'commission_type' => 'percentage',
            'commission_value' => 5, 'status' => 'active',
        ]);

        $category = Category::create(['name' => 'Test Category', 'slug' => 'test-category', 'status' => true]);

        $this->product = Product::create([
            'shop_id' => $this->shop->id, 'category_id' => $category->id,
            'name' => 'Test Product', 'slug' => 'test-product', 'price' => 100000,
            'current_stock' => 50, 'status' => 'approved', 'published' => true,
        ]);
    }

    public function test_customer_can_add_to_cart(): void
    {
        $this->actingAs($this->customer);

        $response = $this->post('/cart/add', [
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $response->assertRedirect(route('cart.index'));
        $this->assertDatabaseHas('carts', [
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);
    }

    public function test_cart_page_loads(): void
    {
        $this->actingAs($this->customer);
        $response = $this->get('/cart');
        $response->assertStatus(200);
    }

    public function test_checkout_page_loads_with_cart_items(): void
    {
        $this->actingAs($this->customer);

        $this->post('/cart/add', ['product_id' => $this->product->id, 'quantity' => 1]);

        $response = $this->get('/checkout');
        $response->assertStatus(200);
    }

    public function test_order_number_is_unique(): void
    {
        $number1 = Order::generateOrderNumber();
        $number2 = Order::generateOrderNumber();
        $this->assertNotEquals($number1, $number2);
    }

    public function test_guest_cannot_access_cart(): void
    {
        $response = $this->get('/cart');
        $response->assertRedirect(route('login'));
    }

    public function test_product_detail_page_loads(): void
    {
        $response = $this->get('/products/test-product');
        $response->assertStatus(200);
        $response->assertSee('Test Product');
    }

    public function test_product_listing_loads(): void
    {
        $response = $this->get('/products');
        $response->assertStatus(200);
    }
}
