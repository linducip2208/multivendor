<?php

declare(strict_types=1);

namespace App\Plugins;

use App\Payments\GatewayRegistry;
use Illuminate\Support\Facades\Schema;

/**
 * Loader + registry plugin gateway / Plugin loader + gateway registry.
 *
 * - Manifest: app/Plugins/<kode>/plugin.json (name/version/provider/
 *   capabilities: countries/currencies/methods + settings/routes/webhooks).
 * - Aktif/nonaktif: SystemSetting "plugins.enabled" (daftar kode koma).
 *   Bila setting kosong/belum ada → semua manifest aktif (default aman).
 * - Registrasi: registerGateways() mendaftarkan gateway app/Payments ke
 *   GatewayRegistry VIA manifest — tanpa hardcode daftar baru di PHP.
 * - Health: providerHealth() JUJUR — hanya cek lokal (kelas ada, isEnabled(),
 *   baris providers aktif). TANPA kredensial live, TANPA HTTP keluar.
 *   Status "unknown" bila belum dikonfigurasi.
 */
final class PluginManager
{
    public const ENABLED_KEY = 'plugins.enabled';

    /** @var PluginManifest[]|null */
    private ?array $manifestCache = null;

    /** @return PluginManifest[] terurut priority menaik */
    public function all(): array
    {
        if ($this->manifestCache !== null) {
            return $this->manifestCache;
        }

        $manifests = [];
        foreach (glob($this->pluginsPath().'/*/plugin.json') ?: [] as $file) {
            try {
                $data = json_decode((string) file_get_contents($file), true);
                if (! is_array($data)) {
                    continue;
                }
                $manifests[] = PluginManifest::fromArray($data, (string) $file);
            } catch (\Throwable) {
                continue;
            }
        }

        usort($manifests, static fn (PluginManifest $a, PluginManifest $b): int => [$a->priority, $a->code] <=> [$b->priority, $b->code]);

        return $this->manifestCache = $manifests;
    }

    public function find(string $code): ?PluginManifest
    {
        foreach ($this->all() as $manifest) {
            if ($manifest->code === strtolower($code)) {
                return $manifest;
            }
        }

        return null;
    }

    /** @return string[] kode aktif */
    public function enabledCodes(): array
    {
        $raw = $this->setting(self::ENABLED_KEY);
        if ($raw === null || trim($raw) === '') {
            return array_map(static fn (PluginManifest $m): string => $m->code, $this->all());
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (string $c): string => strtolower(trim($c)),
            explode(',', $raw)
        ))));
    }

    /** @return PluginManifest[] */
    public function active(): array
    {
        $enabled = $this->enabledCodes();

        return array_values(array_filter(
            $this->all(),
            static fn (PluginManifest $m): bool => in_array($m->code, $enabled, true)
        ));
    }

    public function isEnabled(string $code): bool
    {
        return in_array(strtolower($code), $this->enabledCodes(), true);
    }

    public function enable(string $code): void
    {
        $codes = array_unique([...$this->enabledCodes(), strtolower($code)]);
        $this->persist($codes);
    }

    public function disable(string $code): void
    {
        $codes = array_values(array_diff($this->enabledCodes(), [strtolower($code)]));
        $this->persist($codes);
    }

    /**
     * Daftarkan gateway app/Payments ke registry VIA manifest.
     * Tanpa hardcode daftar baru — sumber tunggal adalah plugin.json.
     */
    public function registerGateways(GatewayRegistry $registry): GatewayRegistry
    {
        foreach ($this->active() as $manifest) {
            if (! class_exists($manifest->gateway)) {
                continue;
            }
            $class = $manifest->gateway;
            $registry->register($manifest->code, static fn () => new $class(), [
                'currencies' => $manifest->currencies,
                'countries' => $manifest->countries,
                'methods' => $manifest->methods,
            ]);
        }

        return $registry;
    }

    /**
     * Health jujur per provider — aman, tanpa kredensial live, tanpa HTTP.
     *
     * @return array{code:string,status:string,configured:bool,enabled:bool,message_id:string,message_en:string}
     */
    public function providerHealth(string $code): array
    {
        $manifest = $this->find($code);
        if ($manifest === null) {
            return [
                'code' => strtolower($code),
                'status' => 'error',
                'configured' => false,
                'enabled' => false,
                'message_id' => 'Plugin tidak dikenal.',
                'message_en' => 'Unknown plugin.',
            ];
        }

        if (! $this->isEnabled($code)) {
            return [
                'code' => $manifest->code,
                'status' => 'disabled',
                'configured' => false,
                'enabled' => false,
                'message_id' => 'Plugin nonaktif.',
                'message_en' => 'Plugin is disabled.',
            ];
        }

        if (! class_exists($manifest->gateway)) {
            return [
                'code' => $manifest->code,
                'status' => 'error',
                'configured' => false,
                'enabled' => true,
                'message_id' => 'Kelas gateway tidak ditemukan.',
                'message_en' => 'Gateway class not found.',
            ];
        }

        // 1) Gateway menyatakan sendiri (isEnabled) — cek lokal tanpa jaringan.
        try {
            $gateway = new ($manifest->gateway)();
            if (method_exists($gateway, 'isEnabled')) {
                if (! $gateway->isEnabled()) {
                    return $this->unknown($manifest, 'Kredensial belum dikonfigurasi; status unknown.');
                }

                return $this->ok($manifest, 'Kredensial terdeteksi (keberadaan saja, nilai tidak ditampilkan).');
            }
        } catch (\Throwable) {
            return $this->unknown($manifest, 'Gateway tidak dapat diinstansiasi tanpa konfigurasi.');
        }

        // 2) Gateway ID (stub adapter): anggap unknown kecuali ada baris providers aktif.
        if ($this->hasActiveProviderRow($manifest->code)) {
            return $this->ok($manifest, 'Baris provider aktif ditemukan di database.');
        }

        return $this->unknown($manifest, 'Belum ada konfigurasi provider; status unknown.');
    }

    /**
     * Ringkasan semua provider (ID + intl) untuk admin UI.
     *
     * @return array<int, array<string,mixed>>
     */
    public function providersOverview(): array
    {
        $rows = [];
        foreach ($this->all() as $manifest) {
            $health = $this->providerHealth($manifest->code);
            $rows[] = [
                'code' => $manifest->code,
                'name' => $manifest->name,
                'version' => $manifest->version,
                'provider' => $manifest->provider,
                'gateway' => $manifest->gateway,
                'countries' => $manifest->countries,
                'currencies' => $manifest->currencies,
                'methods' => $manifest->methods,
                'priority' => $manifest->priority,
                'test_mode_supported' => $manifest->testModeSupported,
                'settings' => $manifest->settings,
                'routes' => $manifest->routes,
                'webhooks' => $manifest->webhooks,
                'plugin_enabled' => $this->isEnabled($manifest->code),
                'db_active' => $this->hasActiveProviderRow($manifest->code),
                'configured' => $health['configured'],
                'health' => $health['status'],
                'health_id' => $health['message_id'],
                'health_en' => $health['message_en'],
            ];
        }

        return $rows;
    }

    /**
     * Log webhook pembayaran terbaru — tanpa isi rahasia.
     *
     * @return array<int, array<string,mixed>>
     */
    public function paymentLog(int $limit = 20): array
    {
        try {
            if (! Schema::hasTable('payment_webhook_callbacks')) {
                return [];
            }
            $rows = \Illuminate\Support\Facades\DB::table('payment_webhook_callbacks')
                ->orderByDesc('id')->limit(max(1, min(50, $limit)))
                ->get(['id', 'provider_id', 'gateway_transaction_id', 'processing_result', 'created_at']);

            return $rows->map(static fn ($r): array => (array) $r)->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param string[] $codes */
    private function persist(array $codes): void
    {
        try {
            \App\Models\SystemSetting::set(self::ENABLED_KEY, implode(',', $codes));
        } catch (\Throwable) {
        }
        $this->manifestCache = null;
    }

    private function setting(string $key): ?string
    {
        try {
            if (! Schema::hasTable('system_settings')) {
                return null;
            }

            return \App\Models\SystemSetting::get($key);
        } catch (\Throwable) {
            return null;
        }
    }

    private function hasActiveProviderRow(string $code): bool
    {
        try {
            if (! Schema::hasTable('providers')) {
                return false;
            }

            return \App\Models\Provider::query()
                ->where('type', 'payment')
                ->where('is_active', true)
                ->where(function ($q) use ($code): void {
                    $q->where('api_format', $code)->orWhere('api_format', 'like', '%'.$code.'%');
                })->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{code:string,status:string,configured:bool,enabled:bool,message_id:string,message_en:string} */
    private function unknown(PluginManifest $m, string $messageId): array
    {
        return [
            'code' => $m->code,
            'status' => 'unknown',
            'configured' => false,
            'enabled' => true,
            'message_id' => $messageId.' / Status unknown.',
            'message_en' => 'Not configured yet; status is unknown.',
        ];
    }

    /** @return array{code:string,status:string,configured:bool,enabled:bool,message_id:string,message_en:string} */
    private function ok(PluginManifest $m, string $messageId): array
    {
        return [
            'code' => $m->code,
            'status' => 'configured',
            'configured' => true,
            'enabled' => true,
            'message_id' => $messageId,
            'message_en' => 'Credentials detected (presence only, values never shown).',
        ];
    }

    private function pluginsPath(): string
    {
        return dirname(__DIR__).'/Plugins';
    }
}
