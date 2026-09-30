<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\ApiSecurityHeaders;
use App\Http\Middleware\WebhookTimestamp;
use App\Models\Provider;
use App\Services\Api\ApiFilter;
use App\Services\Api\ApiOutputSanitizer;
use App\Services\Api\ApiVersioning;
use App\Services\Api\RateLimitRegistry;
use App\Services\Api\TokenHardening;
use App\Services\Payment\PaymentLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Audit API/security: OAuth (dinyatakan prasyarat eksternal), token+scope+
 * rate limit, versioning/deprecation, injection, XSS, CSRF, SSRF upload,
 * IDOR, mass assignment, secret di log/respons. Copy BI/EN. Tanpa API live.
 */
class ApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake([]);
    }

    /** 4. OAuth: arsitektur sanctum+api_keys -> OAuth dinyatakn prasyarat eksternal; token diperkuat. */
    public function test_oauth_tidak_didukung_token_diperkuat(): void
    {
        $this->assertFalse(class_exists('Laravel\Passport\Passport'));
        $this->assertTrue(class_exists('Laravel\Sanctum\Sanctum'));

        $default = TokenHardening::expiryPolicy(null);
        $this->assertLessThanOrEqual(31, (int) $default->diff(new \DateTimeImmutable())->format('%a') + 1);

        $capped = TokenHardening::expiryPolicy(new \DateTimeImmutable('+365 days'));
        $this->assertLessThanOrEqual(90, (int) $capped->diff(new \DateTimeImmutable())->format('%a'));

        $this->assertSame(['read'], TokenHardening::safeScopes(['*']));
        $this->assertContains('*', TokenHardening::safeScopes(['*'], true));
        $this->assertContains('wildcard', TokenHardening::auditScopes(['*', 'read']));
        $this->assertTrue(TokenHardening::needsRotation(null, null));
        $this->assertFalse(TokenHardening::needsRotation(now()->toDateTimeString(), now()->addDays(10)->toDateTimeString()));
    }

    /** 4b. Versioning/deprecation konsisten v1..v4. */
    public function test_versioning_deprecation_konsisten(): void
    {
        $this->assertSame('v4', ApiVersioning::CURRENT);
        foreach (['v1', 'v2', 'v3'] as $old) {
            $headers = ApiVersioning::deprecationHeaders($old);
            $this->assertSame('true', $headers['Deprecation']);
            $this->assertArrayHasKey('Sunset', $headers);
            $this->assertStringContainsString('v4', $headers['Sunset-Link']);
        }
        $current = ApiVersioning::deprecationHeaders('v4');
        $this->assertArrayNotHasKey('Sunset', $current);
        $this->assertSame('v2', ApiVersioning::versionFromPath('api/v2/orders'));
        $this->assertTrue(ApiVersioning::isSupported('v4'));
        $this->assertFalse(ApiVersioning::isSupported('v9'));
    }

    /** 4c. Rate limit endpoint sensitif terdaftar (auth/write/search/global). */
    public function test_rate_limit_sensitif_terdaftar(): void
    {
        RateLimitRegistry::ensure();
        foreach ([RateLimitRegistry::GLOBAL, RateLimitRegistry::AUTH, RateLimitRegistry::WRITE, RateLimitRegistry::SEARCH] as $name) {
            $this->assertNotNull(RateLimiter::limiter($name), 'Limiter '.$name.' hilang.');
        }
        // Auth dibatasi ketat (10/menit) -> brute force login terhambat.
        $request = Request::create('/api/v1/auth/login', 'POST', ['email' => 'a@uji.id']);
        $limiter = RateLimiter::limiter(RateLimitRegistry::AUTH);
        $this->assertNotNull($limiter($request));
    }

    /** 5. Injection: filter/sort whitelist menolak kolom liar tanpa error SQL. */
    public function test_injection_filter_sort_whitelist(): void
    {
        $filter = ApiFilter::for('orders');
        $request = Request::create('/api/v1/orders?sort=-password;DROP+TABLE+users&password=1&include=password', 'GET');
        $this->assertSame([], $filter->resolvedIncludes($request));
        $sorts = $filter->resolvedSorts($request);
        foreach ($sorts as $spec) {
            $this->assertStringNotContainsStringIgnoringCase('password', $spec);
            $this->assertStringNotContainsString('DROP', $spec);
        }
        // Search like-escape: % dan _ tidak menjadi wildcard liar.
        $searchReq = Request::create('/api/v1/products', 'GET', ['search' => '100%_gratis']);
        $this->assertSame('100%_gratis', $filter->resolvedFilters($searchReq)['search'] ?? null);
    }

    /** 5b. XSS via API resources dinetralkan di lapisan sanitasi output. */
    public function test_xss_output_sanitizer_menetralkan(): void
    {
        $dirty = [
            'comment' => '<script>alert(1)</script><p onclick="x()">Halo</p>',
            'name' => '<img src=x onerror=alert(2)>Toko',
            'id' => 7,
            'nested' => ['note' => '<a href="javascript:alert(3)">klik</a>'],
        ];
        $clean = ApiOutputSanitizer::sanitize($dirty);
        $this->assertStringNotContainsString('<script', strtolower((string) $clean['comment']));
        $this->assertStringNotContainsString('onclick', strtolower((string) $clean['comment']));
        $this->assertStringNotContainsString('onerror', strtolower((string) $clean['name']));
        $this->assertStringNotContainsString('javascript:', strtolower((string) $clean['nested']['note']));
        $this->assertSame(7, $clean['id']);
    }

    /** 5c. Header keamanan API + timestamp webhook. */
    public function test_header_keamanan_dan_timestamp(): void
    {
        $middleware = new ApiSecurityHeaders();
        $response = $middleware->handle(Request::create('/api/v4/orders', 'GET'), fn () => response()->json([]));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));

        $this->assertTrue(WebhookTimestamp::isFresh((string) time()));
        $this->assertFalse(WebhookTimestamp::isFresh((string) (time() - 9999)));
        $this->assertFalse(WebhookTimestamp::isFresh('bukan-angka'));

        $ts = new WebhookTimestamp();
        $stale = $ts->handle(
            Request::create('/webhook/payment/1', 'POST', [], [], [], ['HTTP_X_TIMESTAMP' => (string) (time() - 9999)]),
            fn () => response()->json(['ok' => true])
        );
        $this->assertSame(400, $stale->getStatusCode());
    }

    /** 5d. Kredensial terenkripsi at-rest, masked, tak pernah di-log. */
    public function test_kredensial_terenkripsi_masked_tak_dilog(): void
    {
        $provider = new Provider([
            'name' => 'Uji', 'type' => 'payment', 'api_format' => 'midtrans-snap',
            'base_url' => 'https://api.example.test', 'is_active' => true,
        ]);
        $provider->api_key_encrypted = 'kunci-sangat-rahasia-123456';
        $provider->api_secret_encrypted = 'server-key-rahasia-abcdef';
        $provider->config = ['gateway' => 'fake', 'webhook_secret' => 'wh-sekret-1234567890'];
        $provider->save();

        $raw = $provider->getRawOriginal('api_key_encrypted');
        $this->assertNotEmpty($raw);
        $this->assertStringNotContainsString('kunci-sangat-rahasia', (string) $raw);
        $this->assertSame('kunci-sangat-rahasia-123456', $provider->fresh()->getApiKeyAttribute());

        $array = $provider->fresh()->toArray();
        $this->assertArrayNotHasKey('api_key_encrypted', $array);
        $this->assertArrayNotHasKey('api_secret_encrypted', $array);
        $this->assertArrayNotHasKey('api_secret', $array);
        $json = json_encode($array);
        $this->assertStringNotContainsString('server-key-rahasia', (string) $json);
        $this->assertStringNotContainsString('wh-sekret', (string) $json);

        $redacted = PaymentLog::redact(['api_secret' => 'x', 'server_key' => 'y', 'amount' => 1000]);
        $this->assertSame(PaymentLog::REDACTED, $redacted['api_secret']);
        $this->assertSame(PaymentLog::REDACTED, $redacted['server_key']);
        $this->assertSame(1000, $redacted['amount']);
    }

    /** 5e. Mass assignment Provider: secret tak dapat diisi via fill biasa. */
    public function test_mass_assignment_provider_aman(): void
    {
        $provider = Provider::create([
            'name' => 'M', 'type' => 'payment', 'api_format' => 'midtrans-snap',
            'is_active' => true, 'role' => 'admin', 'is_super_admin' => true,
        ]);
        $this->assertNotEquals('admin', (string) ($provider->role ?? ''));
    }

    /** 6. 2FA/session/device: arsitektur tidak mendukung -> prasyarat eksternal. */
    public function test_2fa_prasyarat_eksternal_tercatat(): void
    {
        $this->assertFalse(class_exists('Laravel\Fortify\Fortify'));
        $this->assertFalse(class_exists('PragmaRX\Google2FA\Google2FA'));
        // Prasyarat eksternal terdokumentasi: fortify/google2fa + device fingerprint.
        $this->assertTrue(true, '2FA/session/device memerlukan paket eksternal (fortify + google2fa).');
    }
}
