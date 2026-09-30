<?php

declare(strict_types=1);

namespace App\Plugins;

/**
 * Nilai manifest plugin / Plugin manifest value object.
 *
 * Manifest dibaca dari app/Plugins/<kode>/plugin.json — TANPA hardcode
 * daftar gateway di PHP. Kunci wajib: name, version, code, gateway,
 * countries, currencies, methods. Kunci opsional: provider, priority,
 * test_mode_supported, settings, routes, webhooks.
 */
final class PluginManifest
{
    /**
     * @param string[] $countries   ISO 3166-1 alpha-2, mis. ["ID"]
     * @param string[] $currencies  ISO 4217, mis. ["IDR"]
     * @param string[] $methods     Nilai baku App\Payments\PaymentMethod
     * @param string[] $settings    Kunci env/config tanpa nilai rahasia
     * @param string[] $routes      Rute yang didaftarkan plugin (metadata)
     * @param string[] $webhooks    Event webhook yang didukung plugin
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $version,
        public readonly string $provider,
        public readonly string $gateway,
        public readonly array $countries = [],
        public readonly array $currencies = [],
        public readonly array $methods = [],
        public readonly int $priority = 100,
        public readonly bool $testModeSupported = true,
        public readonly array $settings = [],
        public readonly array $routes = [],
        public readonly array $webhooks = [],
    ) {}

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data, string $source = ''): self
    {
        foreach (['name', 'version', 'code', 'gateway'] as $required) {
            if (! isset($data[$required]) || trim((string) $data[$required]) === '') {
                throw new \InvalidArgumentException(
                    "Manifest {$source} wajib memiliki kunci \"{$required}\". / Manifest {$source} requires \"{$required}\"."
                );
            }
        }

        return new self(
            code: strtolower((string) ($data['code'] ?? '')),
            name: (string) $data['name'],
            version: (string) $data['version'],
            provider: (string) ($data['provider'] ?? $data['name']),
            gateway: (string) $data['gateway'],
            countries: array_values(array_map('strtoupper', (array) ($data['countries'] ?? $data['capabilities']['countries'] ?? []))),
            currencies: array_values(array_map('strtoupper', (array) ($data['currencies'] ?? $data['capabilities']['currencies'] ?? []))),
            methods: array_values((array) ($data['methods'] ?? $data['capabilities']['methods'] ?? [])),
            priority: (int) ($data['priority'] ?? 100),
            testModeSupported: filter_var($data['test_mode_supported'] ?? true, FILTER_VALIDATE_BOOLEAN),
            settings: array_values((array) ($data['settings'] ?? [])),
            routes: array_values((array) ($data['routes'] ?? [])),
            webhooks: array_values((array) ($data['webhooks'] ?? [])),
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'version' => $this->version,
            'provider' => $this->provider,
            'gateway' => $this->gateway,
            'countries' => $this->countries,
            'currencies' => $this->currencies,
            'methods' => $this->methods,
            'priority' => $this->priority,
            'test_mode_supported' => $this->testModeSupported,
            'settings' => $this->settings,
            'routes' => $this->routes,
            'webhooks' => $this->webhooks,
        ];
    }
}
