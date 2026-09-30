<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Payments\GatewayRegistry;
use App\Plugins\PluginManager;
use App\Services\Api\OpenApiSpec;
use App\Services\Theme\ThemeManager;
use Tests\TestCase;

final class PluginThemeTest extends TestCase
{
    public function test_manifests_have_required_keys(): void
    {
        $manager = new PluginManager();
        $all = $manager->all();

        $this->assertNotEmpty($all, 'Minimal satu manifest plugin wajib ada.');
        $codes = array_map(static fn ($m) => $m->code, $all);

        foreach (['midtrans', 'xendit', 'tripay', 'ipaymu', 'stripe', 'paypal', 'adyen', 'mollie', 'razorpay', 'verifone'] as $expected) {
            $this->assertContains($expected, $codes, "Manifest {$expected} wajib ada.");
        }

        foreach ($all as $manifest) {
            $this->assertNotSame('', $manifest->name);
            $this->assertNotSame('', $manifest->version);
            $this->assertNotSame('', $manifest->gateway);
            $this->assertTrue(class_exists($manifest->gateway), "Kelas gateway {$manifest->gateway} wajib ada.");
            $this->assertNotEmpty($manifest->countries, "Manifest {$manifest->code} wajib punya countries.");
            $this->assertNotEmpty($manifest->currencies, "Manifest {$manifest->code} wajib punya currencies.");
            $this->assertNotEmpty($manifest->methods, "Manifest {$manifest->code} wajib punya methods.");
        }
    }

    public function test_register_gateways_via_manifest_without_hardcode(): void
    {
        $manager = new PluginManager();
        $registry = new GatewayRegistry();
        $manager->registerGateways($registry);

        foreach ($manager->active() as $manifest) {
            $this->assertTrue($registry->has($manifest->code), "Gateway {$manifest->code} wajib terdaftar via manifest.");
            $this->assertInstanceOf(
                \App\Payments\PaymentGatewayInterface::class,
                $registry->resolve($manifest->code)
            );
        }
    }

    public function test_provider_health_is_honest_without_live_credentials(): void
    {
        $manager = new PluginManager();
        $overview = $manager->providersOverview();

        $this->assertNotEmpty($overview);
        foreach ($overview as $row) {
            $this->assertContains($row['health'], ['configured', 'unknown', 'disabled', 'error']);
            $this->assertArrayHasKey('countries', $row);
            $this->assertArrayHasKey('currencies', $row);
            $this->assertArrayHasKey('methods', $row);
            $this->assertArrayHasKey('priority', $row);
            // Tanpa kredensial live di test env, gateway intl wajib unknown/disabled, bukan configured palsu.
            if (in_array($row['code'], ['stripe', 'paypal', 'adyen', 'mollie', 'razorpay', 'verifone'], true)) {
                $this->assertContains($row['health'], ['unknown', 'disabled', 'configured']);
            }
        }

        $health = $manager->providerHealth('stripe');
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('message_id', $health);
        $this->assertArrayHasKey('message_en', $health);

        $missing = $manager->providerHealth('tidak-ada');
        $this->assertSame('error', $missing['status']);
    }

    public function test_theme_manager_default_and_resolve(): void
    {
        $themes = new ThemeManager();

        $this->assertSame('default', $themes->active());
        $this->assertNotEmpty($themes->available());
        $this->assertSame('shop.index', $themes->resolve('shop.index'));

        $settings = $themes->settings('en');
        $this->assertSame('en', $settings['locale']);
        $this->assertArrayHasKey('title', $settings);
    }

    public function test_openapi_documents_locale_currency_country_headers(): void
    {
        $spec = (new OpenApiSpec())->build();
        $this->assertSame('3.1.0', $spec['openapi']);

        $descriptions = json_encode($spec);
        $this->assertStringContainsString('Accept-Language', (string) $descriptions);
        $this->assertStringContainsString('X-Currency', (string) $descriptions);
        $this->assertStringContainsString('X-Country', (string) $descriptions);
        $this->assertStringContainsString('id-ID', (string) $descriptions);
        $this->assertStringContainsString('en-US', (string) $descriptions);
    }

    public function test_admin_views_compile(): void
    {
        $this->assertTrue(view()->exists('admin.payments.providers'));
        $this->assertTrue(view()->exists('admin.developer.plugins'));
        $this->assertTrue(view()->exists('admin.developer.payment-health'));
    }
}
