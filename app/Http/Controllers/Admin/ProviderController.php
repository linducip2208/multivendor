<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Integration providers.
 *
 * Credentials are write-only from the UI's point of view: the list and edit
 * forms render a mask derived from the stored ciphertext, never the decrypted
 * value, and an empty credential field on update means "keep the current one".
 * `api_format` is validated against the formats the adapter layer can actually
 * drive, and connectivity is probed on save.
 */
class ProviderController extends Controller
{
    private const TYPES = ['payment', 'shipping', 'sms', 'mail', 'ai', 'storage'];

    private const FORMATS = [
        'payment' => ['midtrans-snap', 'midtrans', 'xendit', 'manual', 'manual_transfer'],
        'shipping' => ['rajaongkir', 'jne', 'tiki', 'pos', 'anteraja', 'shipment', 'manual_shipping'],
        'sms' => ['twilio', 'nexmo', 'zenziva', 'generic-http'],
        'mail' => ['ses', 'smtp', 'sendgrid', 'mailgun', 'generic-http'],
        'ai' => ['openai-compatible', 'anthropic', 'gemini', 'custom'],
        'storage' => ['s3', 'local', 'cloudinary', 'generic-http'],
    ];

    public function index(Request $request): View
    {
        $query = Provider::query();

        if ($request->filled('type')) {
            $query->where('type', (string) $request->query('type'));
        }

        $providers = $query->orderBy('type')->orderBy('name')->paginate(15)->withQueryString();

        return view('admin.providers.index', [
            'providers' => $providers,
            'types' => self::TYPES,
            'rows' => $providers->getCollection()->map(fn (Provider $provider): array => $this->row($provider))->all(),
        ]);
    }

    public function create(): View
    {
        return view('admin.providers.create', [
            'presets' => $this->loadPresets(),
            'types' => self::TYPES,
            'formats' => self::FORMATS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(self::TYPES)],
            'api_format' => ['required', 'string', 'max:50'],
            'base_url' => ['nullable', 'url', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:1000'],
            'api_secret' => ['nullable', 'string', 'max:1000'],
            'extra_headers' => ['nullable', 'string', 'max:4000'],
            'config' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], $this->formatMessages());

        $this->assertFormat($validated['type'], $validated['api_format'], 'api_format');

        $provider = new Provider();
        $this->apply($provider, $validated, $request);
        $provider->save();

        $probe = $this->probe($provider);

        app(AuditLogger::class)->log('provider.created', $provider, [], [
            'type' => $provider->type,
            'format' => $provider->api_format,
            'probe' => $probe['status'],
        ], auth('admin')->id());

        return redirect()
            ->route('admin.providers.index')
            ->with('success', 'Provider "'.$provider->name.'" ditambahkan. '.$probe['message'])
            ->with('provider_probe', $probe);
    }

    public function edit(Provider $provider): View
    {
        return view('admin.providers.edit', [
            'provider' => $this->row($provider),
            'presets' => $this->loadPresets(),
            'types' => self::TYPES,
            'formats' => self::FORMATS,
        ]);
    }

    public function update(Request $request, Provider $provider): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(self::TYPES)],
            'api_format' => ['required', 'string', 'max:50'],
            'base_url' => ['nullable', 'url', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:1000'],
            'api_secret' => ['nullable', 'string', 'max:1000'],
            'extra_headers' => ['nullable', 'string', 'max:4000'],
            'config' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], $this->formatMessages());

        $this->assertFormat($validated['type'], $validated['api_format'], 'api_format');

        $before = $provider->only(['name', 'type', 'api_format', 'base_url', 'is_active']);

        $this->apply($provider, $validated, $request);
        $provider->save();

        $probe = $this->probe($provider);

        app(AuditLogger::class)->log('provider.updated', $provider, $before, [
            'type' => $provider->type,
            'format' => $provider->api_format,
            'probe' => $probe['status'],
        ], auth('admin')->id());

        return back()->with('success', 'Provider "'.$provider->name.'" diperbarui. '.$probe['message'])->with('provider_probe', $probe);
    }

    public function destroy(Provider $provider): RedirectResponse
    {
        $snapshot = ['name' => (string) $provider->name, 'type' => (string) $provider->type];
        $provider->delete();

        app(AuditLogger::class)->log('provider.deleted', null, $snapshot, [], auth('admin')->id());

        return back()->with('success', 'Provider dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Provider $provider): array
    {
        return [
            'id' => (int) $provider->id,
            'name' => (string) $provider->name,
            'type' => (string) $provider->type,
            'api_format' => (string) $provider->api_format,
            'base_url' => (string) ($provider->base_url ?? ''),
            'host' => (string) (parse_url((string) $provider->base_url, PHP_URL_HOST) ?: '-'),
            'api_key_mask' => $provider->getApiKeyAttribute() !== null ? $this->mask((string) $provider->getApiKeyAttribute()) : '',
            'api_secret_mask' => $provider->getApiSecretAttribute() !== null ? $this->mask((string) $provider->getApiSecretAttribute()) : '',
            'has_key' => $provider->getApiKeyAttribute() !== null,
            'has_secret' => $provider->getApiSecretAttribute() !== null,
            'is_active' => (bool) $provider->is_active,
            'is_default' => (bool) $provider->is_default,
            'config' => is_array($provider->config) ? $provider->config : [],
            'extra_headers' => is_array($provider->extra_headers) ? $provider->extra_headers : [],
            'description' => (string) ($provider->description ?? ''),
        ];
    }

    private function mask(string $value): string
    {
        if (strlen($value) <= 8) {
            return str_repeat('•', 8);
        }

        return substr($value, 0, 3).str_repeat('•', 8).substr($value, -3);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function apply(Provider $provider, array $validated, Request $request): void
    {
        $provider->name = (string) $validated['name'];
        $provider->type = (string) $validated['type'];
        $provider->api_format = (string) $validated['api_format'];
        $provider->base_url = $validated['base_url'] ?? null;
        $provider->extra_headers = $this->decodeJson($validated['extra_headers'] ?? null);
        $provider->config = $this->decodeJson($validated['config'] ?? null);
        $provider->is_active = $request->boolean('is_active');
        $provider->is_default = $request->boolean('is_default');
        $provider->description = $validated['description'] ?? null;

        if (($validated['api_key'] ?? '') !== '') {
            $provider->setApiKeyEncryptedAttribute((string) $validated['api_key']);
        }

        if (($validated['api_secret'] ?? '') !== '') {
            $provider->setApiSecretEncryptedAttribute((string) $validated['api_secret']);
        }
    }

    /**
     * @return array<string, string>
     */
    private function decodeJson(mixed $value): ?array
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function assertFormat(string $type, string $format, string $field): void
    {
        $allowed = self::FORMATS[$type] ?? [];

        if (! in_array($format, $allowed, true)) {
            throw new \Illuminate\Validation\ValidationException(
                request(),
                [$field => ['Format "'.$format.'" tidak didukung untuk tipe '.$type.'. Pilihan: '.implode(', ', $allowed).'.']],
            );
        }
    }

    /**
     * @return array{status: string, message: string}
     */
    private function probe(Provider $provider): array
    {
        $base = rtrim((string) $provider->base_url, '/');

        if ($base === '') {
            return ['status' => 'skipped', 'message' => 'Koneksi tidak diuji karena base URL kosong.'];
        }

        try {
            $key = (string) $provider->getApiKeyAttribute();
            $response = \Illuminate\Support\Facades\Http::acceptJson()
                ->timeout(6)
                ->connectTimeout(3)
                ->when($key !== '', fn ($request) => $request->withHeaders(['Authorization' => 'Bearer '.$key]))
                ->get($base.'/');

            return [
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
                'message' => $response->successful()
                    ? 'Koneksi berhasil diuji (HTTP '.$response->status().').'
                    : 'Koneksi dijawab HTTP '.$response->status().'. Periksa base URL dan format API.',
            ];
        } catch (\Throwable) {
            return ['status' => 'unreachable', 'message' => 'Koneksi tidak dapat dihubungi dari server ini.'];
        }
    }

    /**
     * @return array<string, string>
     */
    private function formatMessages(): array
    {
        return [
            'api_format' => 'Format API harus didukung oleh layer adapter untuk tipe provider ini.',
            'extra_headers' => 'Header tambahan harus berupa JSON objek yang valid.',
            'config' => 'Konfigurasi harus berupa JSON objek yang valid.',
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function loadPresets(): array
    {
        $payment = [];
        $shipping = [];
        $ai = [];

        $paymentFile = storage_path('app/presets/payment-presets.json');
        $shippingFile = storage_path('app/presets/shipping-presets.json');
        $aiFile = storage_path('app/presets/ai-presets.json');

        if (file_exists($paymentFile)) {
            $data = json_decode((string) file_get_contents($paymentFile), true);
            $payment = is_array($data['payment-presets'] ?? null) ? $data['payment-presets'] : [];
        }

        if (file_exists($shippingFile)) {
            $data = json_decode((string) file_get_contents($shippingFile), true);
            $shipping = is_array($data['shipping-presets'] ?? null) ? $data['shipping-presets'] : [];
        }

        if (file_exists($aiFile)) {
            $data = json_decode((string) file_get_contents($aiFile), true);
            $ai = is_array($data['ai-presets'] ?? null) ? $data['ai-presets'] : [];
        }

        return ['payment-presets' => $payment, 'shipping-presets' => $shipping, 'ai-presets' => $ai];
    }
}
