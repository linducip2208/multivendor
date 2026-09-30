<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\FileManagerController;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\PaymentWebhookCallback;
use App\Models\Provider;
use App\Models\Shop;
use App\Models\User;
use App\Services\HtmlSanitizer;
use App\Services\Payment\PaymentLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Audit keamanan mandiri (self-contained, sqlite :memory:).
 *
 * Meliputi: IDOR (order + address milik pelanggan lain), mass assignment
 * (eskalasi role), XSS (ikon kategori + sanitizer), redaksi secret di log,
 * throttle endpoint sensitif, validasi nama berkas upload, dan verifikasi
 * signature + idempotency webhook pembayaran.
 */
class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(string $email): User
    {
        return User::create([
            'name' => 'Customer',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'customer',
            'status' => 'active',
        ]);
    }

    private function makeShop(): Shop
    {
        $vendor = User::create([
            'name' => 'Vendor',
            'email' => 'vendor-'.uniqid().'@test.id',
            'password' => Hash::make('password'),
            'role' => 'vendor',
            'status' => 'active',
        ]);

        return Shop::create([
            'vendor_id' => $vendor->id,
            'name' => 'Toko Uji',
            'slug' => 'toko-uji-'.uniqid(),
            'status' => 'active',
        ]);
    }

    /** IDOR: pelanggan tidak boleh membuka pesanan milik pelanggan lain. */
    public function test_customer_cannot_view_another_customer_order(): void
    {
        $shop = $this->makeShop();
        $alice = $this->makeCustomer('alice@test.id');
        $bob = $this->makeCustomer('bob@test.id');

        $order = Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'customer_id' => $bob->id,
            'shop_id' => $shop->id,
            'total' => 100000,
        ]);

        $this->actingAs($alice)->get('/orders/'.$order->id)->assertForbidden();
        $this->actingAs($bob)->get('/orders/'.$order->id)->assertOk();
    }

    /** IDOR: pelanggan tidak boleh menghapus alamat milik pelanggan lain. */
    public function test_customer_cannot_delete_another_customer_address(): void
    {
        $alice = $this->makeCustomer('alice@test.id');
        $bob = $this->makeCustomer('bob@test.id');

        $address = CustomerAddress::create([
            'customer_id' => $bob->id,
            'label' => 'Rumah',
            'receiver_name' => 'Bob',
            'receiver_phone' => '0811',
            'address' => 'Jl. Mawar 1',
            'city' => 'Jakarta',
            'province' => 'DKI',
        ]);

        $this->actingAs($alice)->delete('/profile/address/'.$address->id)->assertForbidden();
        $this->assertDatabaseHas('customer_addresses', ['id' => $address->id]);
    }

    /** Mass assignment: parameter role diabaikan saat update profil. */
    public function test_profile_update_ignores_role_escalation(): void
    {
        $alice = $this->makeCustomer('alice@test.id');

        $this->actingAs($alice)->put('/profile', [
            'name' => 'Alice Baru',
            'phone' => '0812',
            'role' => 'admin',
            'is_super_admin' => true,
        ])->assertRedirect();

        $this->assertSame('customer', $alice->fresh()->role);
        $this->assertSame('Alice Baru', $alice->fresh()->name);
    }

    /** XSS: ikon kategori SVG berbahaya tidak boleh lolos mentah ke HTML. */
    public function test_malicious_category_icon_is_neutralized(): void
    {
        $category = Category::create(['name' => 'Uji', 'slug' => 'uji-xss', 'status' => true]);
        $category->forceFill(['icon' => '<svg onload=alert(1)><script>alert(2)</script>'])->save();

        $html = (string) $this->blade(
            '<x-storefront.category-tiles :categories="$categories" />',
            ['categories' => collect([$category->fresh()])]
        );

        $this->assertStringNotContainsString('onload', strtolower($html));
        $this->assertStringNotContainsString('<script', strtolower($html));
        $this->assertStringContainsString('Uji', $html);
    }

    /** XSS: sanitizer wajib melucuti script + event handler + javascript: href. */
    public function test_html_sanitizer_strips_active_content(): void
    {
        $clean = app(HtmlSanitizer::class)->sanitize(
            '<p onclick="alert(1)">Halo</p><script>alert(2)</script>'
            .'<a href="javascript:alert(3)">klik</a><img src="x" onerror="alert(4)">'
        );

        $this->assertStringNotContainsString('<script', strtolower((string) $clean));
        $this->assertStringNotContainsString('onclick', strtolower((string) $clean));
        $this->assertStringNotContainsString('onerror', strtolower((string) $clean));
        $this->assertStringNotContainsString('javascript:', strtolower((string) $clean));
        $this->assertStringContainsString('Halo', (string) $clean);
    }

    /** Secret/PII: PaymentLog wajib meredaksi kredensial dan kontak. */
    public function test_payment_log_redacts_secrets_and_pii(): void
    {
        $redacted = PaymentLog::redact([
            'api_secret' => 's3cr3t',
            'email' => 'user@test.id',
            'phone' => '081234567',
            'x-callback-signature' => 'sig',
            'amount' => 150000,
            'order_id' => 'ORD-1',
        ]);

        $this->assertSame(PaymentLog::REDACTED, $redacted['api_secret']);
        $this->assertSame(PaymentLog::REDACTED, $redacted['email']);
        $this->assertSame(PaymentLog::REDACTED, $redacted['phone']);
        $this->assertSame(PaymentLog::REDACTED, $redacted['x-callback-signature']);
        $this->assertSame(150000, $redacted['amount']);
        $this->assertSame('ORD-1', $redacted['order_id']);
        $this->assertStringContainsString(PaymentLog::REDACTED, PaymentLog::maskBearer('Bearer abc123'));
    }

    private function postRouteMiddleware(string $uri): array
    {
        foreach (Route::getRoutes()->getRoutesByMethod()['POST'] ?? [] as $routeUri => $route) {
            if (ltrim($routeUri, '/') === ltrim($uri, '/')) {
                return $route->gatherMiddleware();
            }
        }

        $this->fail('Rute POST '.$uri.' tidak ditemukan.');

        return [];
    }

    /** Rate limiting: endpoint sensitif wajib memakai throttle. */
    public function test_sensitive_endpoints_have_throttle(): void
    {
        foreach (['admin/login', 'vendor/login', 'vendor/register', 'login', 'register'] as $uri) {
            $middleware = $this->postRouteMiddleware($uri);
            $hasThrottle = collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:'));
            $this->assertTrue($hasThrottle, 'Rute POST /'.$uri.' tanpa throttle.');
        }

        $webhook = $this->postRouteMiddleware('webhook/payment/{provider}');
        $this->assertTrue(
            collect($webhook)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:')),
            'Webhook pembayaran tanpa throttle.'
        );
    }

    /** Upload: nama executable / double-extension wajib ditolak. */
    public function test_risky_upload_names_are_rejected(): void
    {
        $this->assertTrue(FileManagerController::hasRiskyName('shell.php'));
        $this->assertTrue(FileManagerController::hasRiskyName('foto.php.jpg'));
        $this->assertTrue(FileManagerController::hasRiskyName('invoice.pdf.exe'));
        $this->assertTrue(FileManagerController::hasRiskyName('../.env'));
        $this->assertFalse(FileManagerController::hasRiskyName('foto-produk.jpg'));
        $this->assertFalse(FileManagerController::hasRiskyName('dokumen.pdf'));
    }

    private function makePaymentProvider(string $secret): Provider
    {
        $provider = new Provider([
            'name' => 'Midtrans Uji',
            'type' => 'payment',
            'api_format' => 'midtrans-snap',
            'base_url' => 'https://api.example.test',
            'is_active' => true,
        ]);
        $provider->api_secret_encrypted = $secret;
        $provider->save();

        return $provider;
    }

    /** Webhook: signature palsu wajib ditolak 401 dan tidak mencatat callback. */
    public function test_webhook_rejects_invalid_signature(): void
    {
        $provider = $this->makePaymentProvider('server-key-rahasia');

        $response = $this->postJson('/webhook/payment/'.$provider->id, [
            'order_id' => 'ORD-FAKE-1',
            'status_code' => '200',
            'gross_amount' => '100000',
            'signature_key' => 'tanda-tangan-palsu',
            'transaction_status' => 'settlement',
        ]);

        $response->assertStatus(401)->assertJson(['success' => false]);
        $this->assertSame(0, PaymentWebhookCallback::count());
    }

    /** Webhook: callback valid idempoten — kirim ganda tidak mencatat ganda. */
    public function test_webhook_unknown_reference_is_idempotent(): void
    {
        $secret = 'server-key-rahasia';
        $provider = $this->makePaymentProvider($secret);

        $body = [
            'order_id' => 'ORD-'.uniqid(),
            'status_code' => '200',
            'gross_amount' => '75000',
            'transaction_id' => 'trx-'.uniqid(),
            'transaction_status' => 'settlement',
        ];
        $body['signature_key'] = hash('sha512', $body['order_id'].$body['status_code'].$body['gross_amount'].$secret);

        $this->postJson('/webhook/payment/'.$provider->id, $body)->assertStatus(404);
        $this->postJson('/webhook/payment/'.$provider->id, $body)->assertStatus(404);

        $this->assertSame(1, PaymentWebhookCallback::count());
    }
}
