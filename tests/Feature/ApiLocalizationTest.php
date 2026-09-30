<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Api\ApiController;
use App\Http\Middleware\NegotiateCurrency;
use App\Http\Middleware\SetLocale;
use App\Mail\OrderConfirmation;
use App\Mail\WithdrawApprovedMail;
use App\Models\Order;
use App\Models\User;
use App\Models\VendorWithdrawRequest;
use App\Services\Localization\JsBridge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_locale_column_nullable(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'locale'), 'migrasi users.locale belum jalan');
    }

    public function test_locale_resolution_user_over_session_over_browser(): void
    {
        $middleware = new SetLocale;

        // Browser saja -> id.
        $req = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'id-ID,id;q=0.9,en;q=0.5']);
        $this->assertSame('id', $middleware->resolve($req));

        // Session menang atas browser.
        $req = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'id']);
        $req->setLaravelSession(app('session')->driver());
        $req->session()->put('locale', 'en');
        $this->assertSame('en', $middleware->resolve($req));

        // User menang atas session + browser.
        $user = new User;
        $user->forceFill(['locale' => 'en']);
        $req = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'id']);
        $req->setLaravelSession(app('session')->driver());
        $req->session()->put('locale', 'id');
        $req->setUserResolver(fn () => $user);
        $this->assertSame('en', $middleware->resolve($req));
    }

    public function test_setlocale_preserves_cart_session(): void
    {
        $middleware = new SetLocale;
        $req = Request::create('/?lang=en', 'GET');
        $req->setLaravelSession(app('session')->driver());
        $req->session()->put('cart', ['items' => [['id' => 1, 'qty' => 2]]]);
        $req->session()->put('locale', 'id');

        $response = $middleware->handle($req, fn ($r) => response('ok'));

        $this->assertSame(['items' => [['id' => 1, 'qty' => 2]]], $req->session()->get('cart'));
        $this->assertSame('en', $response->headers->get('X-Locale'));
    }

    public function test_currency_negotiation_header_country_session_default(): void
    {
        $middleware = new NegotiateCurrency;

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_X_CURRENCY' => 'USD']);
        $this->assertSame('USD', $middleware->resolveCurrency($req));

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_X_COUNTRY' => 'id']);
        $currency = $middleware->resolveCurrency($req);
        $this->assertMatchesRegularExpression('/^[A-Z]{3}$/', $currency);

        $req = Request::create('/');
        $this->assertSame('IDR', $middleware->resolveCurrency($req));
    }

    public function test_api_envelope_adds_locale_currency_without_breaking_contract(): void
    {
        $controller = new class extends ApiController
        {
            public function probe(Request $request): \Illuminate\Http\JsonResponse
            {
                return $this->ok(['hello' => 'world'], 'OK');
            }
        };

        $req = Request::create('/api/v4/ping', 'GET', [], [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'en',
            'HTTP_X_CURRENCY' => 'USD',
        ]);
        app()->instance('request', $req);

        $response = $controller->probe($req);
        $payload = $response->getData(true);

        // Kontrak existing tetap ada.
        foreach (['success', 'data', 'meta', 'message', 'errors', 'request_id'] as $key) {
            $this->assertArrayHasKey($key, $payload, "kontrak envelope kehilangan {$key}");
        }
        // Aditif lokalisasi.
        $this->assertSame('en', $payload['meta']['locale']);
        $this->assertSame('USD', $payload['meta']['currency']);
        $this->assertArrayHasKey('available_locales', $payload['meta']);
        $this->assertContains('id', $payload['meta']['available_locales']);
        $this->assertContains('en', $payload['meta']['available_locales']);
        $this->assertSame('en', $response->headers->get('X-Locale'));
        $this->assertSame('USD', $response->headers->get('X-Currency'));
    }

    public function test_js_bridge_escapes_xss(): void
    {
        $html = JsBridge::scriptTag('id', ['greet' => '</script><script>alert(1)</script>']);
        $this->assertStringContainsString('window.__trans', $html);
        $this->assertStringNotContainsString('</script><script>', $html);
        $this->assertStringContainsString('\u003C', $html);
    }

    public function test_switcher_preserves_path_and_query(): void
    {
        app()->instance('request', Request::create('/products?category=sepatu&page=2', 'GET'));
        $html = (string) $this->blade(
            '<x-storefront.language-switcher variant="topbar" />'
        );
        // fullUrlWithQuery mempertahankan path+query asal + lang.
        $this->assertStringContainsString('category=sepatu', $html);
        $this->assertStringContainsString('lang=', $html);
        $this->assertStringContainsString('hreflang="id"', $html);
        $this->assertStringContainsString('hreflang="en"', $html);
    }

    public function test_seo_head_has_hreflang_and_canonical(): void
    {
        $html = (string) $this->blade('<x-seo.head title="Sepatu" />');
        $this->assertStringContainsString('rel="canonical"', $html);
        $this->assertStringContainsString('hreflang="id"', $html);
        $this->assertStringContainsString('hreflang="en"', $html);
        $this->assertStringContainsString('hreflang="x-default"', $html);
    }

    public function test_json_ld_adds_in_language(): void
    {
        app()->setLocale('id');
        $html = (string) $this->blade(
            '<x-seo.json-ld :data="$data" />',
            ['data' => ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => 'Sepatu']]
        );
        $this->assertStringContainsString('inLanguage', $html);
        $this->assertStringContainsString('"id"', $html);
    }

    public function test_mail_locale_awareness_order_and_payout(): void
    {
        $customerId = new User;
        $customerId->forceFill([
            'name' => 'Budi', 'email' => 'budi@example.com', 'password' => bcrypt('secret'),
            'role' => 'customer', 'status' => 'active', 'locale' => 'en',
        ])->saveQuietly();

        $order = new Order;
        $order->forceFill([
            'order_number' => 'ORD-TEST-1', 'customer_id' => $customerId->id,
            'total' => 100000, 'order_status' => 'confirmed', 'currency' => 'IDR',
        ]);
        $order->setRelation('customer', $customerId);

        $mail = new OrderConfirmation($order);
        $built = $mail->build();
        $this->assertStringContainsString('Order Confirmed', $built->subject);
        $rendered = $mail->render();
        $this->assertStringContainsString('has been confirmed', $rendered);

        $vendor = new User;
        $vendor->forceFill([
            'name' => 'Vendor', 'email' => 'vendor@example.com', 'password' => bcrypt('secret'),
            'role' => 'vendor', 'status' => 'active', 'locale' => 'id',
        ])->saveQuietly();

        $withdraw = new VendorWithdrawRequest;
        $withdraw->forceFill(['vendor_id' => $vendor->id, 'amount' => 50000, 'status' => 'approved']);
        $withdraw->setRelation('vendor', $vendor);

        $mailId = new WithdrawApprovedMail($withdraw);
        $builtId = $mailId->build();
        $this->assertStringContainsString('Penarikan', $builtId->subject);
        $this->assertStringContainsString('telah disetujui', $mailId->render());
    }
}
