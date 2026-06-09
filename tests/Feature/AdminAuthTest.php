<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        User::create([
            'name' => 'Admin',
            'email' => 'admin@multivendor.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    public function test_admin_login_page_loads(): void
    {
        $response = $this->get('/admin/login');
        $response->assertStatus(200);
    }

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'admin@multivendor.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('admin');
    }

    public function test_admin_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'admin@multivendor.test',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    public function test_vendor_cannot_access_admin_panel(): void
    {
        User::create([
            'name' => 'Vendor',
            'email' => 'vendor@multivendor.test',
            'password' => Hash::make('password'),
            'role' => 'vendor',
            'status' => 'active',
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'vendor@multivendor.test',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_unauthenticated_user_redirected_to_admin_login(): void
    {
        $response = $this->get('/admin/dashboard');
        $response->assertRedirect(route('admin.login'));
    }
}
