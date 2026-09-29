<?php

declare(strict_types=1);

namespace App\Services\Backoffice;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Developer platform: API credentials, webhook endpoints, delivery log and the
 * public event catalogue.
 *
 * API keys are stored as a SHA-256 hash. The plaintext is returned exactly once,
 * at creation, and is unrecoverable afterwards. Webhook secrets are generated
 * server-side and shown masked.
 */
final class DeveloperService
{
    public const SCOPES = [
        'catalog:read' => 'Baca katalog',
        'orders:read' => 'Baca pesanan',
        'orders:write' => 'Kelola pesanan',
        'customers:read' => 'Baca pelanggan',
        'payments:read' => 'Baca pembayaran',
        'webhooks:manage' => 'Kelola webhook',
    ];

    /**
     * @return list<array{event: string, group: string, description: string, payload: list<string>}>
     */
    public function eventCatalogue(): array
    {
        return [
            ['event' => 'order.created', 'group' => 'Pesanan', 'description' => 'Pesanan baru dibuat oleh pelanggan.', 'payload' => ['order_number', 'customer_id', 'shop_id', 'total', 'currency', 'items']],
            ['event' => 'order.paid', 'group' => 'Pesanan', 'description' => 'Pembayaran pesanan berhasil dikonfirmasi.', 'payload' => ['order_number', 'payment_group_id', 'total', 'currency', 'paid_at']],
            ['event' => 'order.shipped', 'group' => 'Pesanan', 'description' => 'Pesanan diserahkan ke kurir.', 'payload' => ['order_number', 'courier', 'tracking_number', 'shipped_at']],
            ['event' => 'order.delivered', 'group' => 'Pesanan', 'description' => 'Pesanan diterima pelanggan.', 'payload' => ['order_number', 'delivered_at']],
            ['event' => 'order.cancelled', 'group' => 'Pesanan', 'description' => 'Pesanan dibatalkan.', 'payload' => ['order_number', 'reason', 'cancelled_at']],
            ['event' => 'payment.paid', 'group' => 'Pembayaran', 'description' => 'Gateway mengonfirmasi pembayaran.', 'payload' => ['payment_number', 'grand_total', 'gateway_reference']],
            ['event' => 'payment.failed', 'group' => 'Pembayaran', 'description' => 'Pembayaran gagal atau kedaluwarsa.', 'payload' => ['payment_number', 'status']],
            ['event' => 'refund.created', 'group' => 'Pembayaran', 'description' => 'Refund dicatat.', 'payload' => ['refund_number', 'order_number', 'amount', 'status']],
            ['event' => 'refund.succeeded', 'group' => 'Pembayaran', 'description' => 'Refund berhasil dikirim ke gateway.', 'payload' => ['refund_number', 'amount', 'succeeded_at']],
            ['event' => 'product.created', 'group' => 'Katalog', 'description' => 'Produk baru ditambahkan.', 'payload' => ['product_id', 'name', 'price']],
            ['event' => 'product.updated', 'group' => 'Katalog', 'description' => 'Data produk diperbarui.', 'payload' => ['product_id', 'changed']],
            ['event' => 'product.stock_changed', 'group' => 'Katalog', 'description' => 'Stok produk berubah.', 'payload' => ['product_id', 'on_hand', 'warehouse_id']],
            ['event' => 'customer.registered', 'group' => 'Pelanggan', 'description' => 'Pelanggan mendaftar.', 'payload' => ['customer_id', 'email', 'registered_at']],
            ['event' => 'review.created', 'group' => 'Katalog', 'description' => 'Ulasan baru menunggu moderasi.', 'payload' => ['review_id', 'product_id', 'rating']],
            ['event' => 'vendor.registered', 'group' => 'Seller', 'description' => 'Pendaftaran vendor baru.', 'payload' => ['shop_id', 'name']],
            ['event' => 'vendor.approved', 'group' => 'Seller', 'description' => 'Vendor disetujui.', 'payload' => ['shop_id', 'name']],
        ];
    }

    /**
     * @return list<string>
     */
    public function eventNames(): array
    {
        return array_column($this->eventCatalogue(), 'event');
    }

    /**
     * @return array<string, mixed>
     */
    public function apiKeys(): array
    {
        try {
            return DB::table('api_keys')->orderByDesc('id')->get()
                ->map(fn ($row): array => [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'prefix' => (string) $row->prefix,
                    'masked' => $this->mask($row->prefix),
                    'scopes' => json_decode((string) $row->scopes, true) ?: [],
                    'created_by' => $row->created_by,
                    'last_used_at' => (string) $row->last_used_at,
                    'expires_at' => (string) $row->expires_at,
                    'revoked_at' => (string) $row->revoked_at,
                    'active' => $row->revoked_at === null && ($row->expires_at === null || $row->expires_at > now()),
                    'created_at' => (string) $row->created_at,
                ])->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{record: array<string, mixed>, plaintext: string}
     */
    public function createApiKey(string $name, array $scopes, ?int $expiresInDays, ?int $actorId): array
    {
        $plain = 'mvk_'.Str::random(40);
        $prefix = substr($plain, 0, 12);

        $id = DB::table('api_keys')->insertGetId([
            'name' => $name,
            'prefix' => $prefix,
            'key_hash' => hash('sha256', $plain),
            'scopes' => json_encode(array_values($scopes)),
            'created_by' => $actorId,
            'expires_at' => $expiresInDays !== null ? now()->addDays($expiresInDays) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(AuditLogger::class)->log('api_key.created', null, [], ['name' => $name, 'prefix' => $prefix], $actorId);

        return [
            'record' => [
                'id' => (int) $id,
                'name' => $name,
                'prefix' => $prefix,
                'scopes' => array_values($scopes),
                'expires_at' => $expiresInDays !== null ? (string) now()->addDays($expiresInDays)->toDateString() : null,
            ],
            'plaintext' => $plain,
        ];
    }

    public function revokeApiKey(int $id, ?int $actorId): bool
    {
        $deleted = DB::table('api_keys')->where('id', $id)->delete() > 0;

        app(AuditLogger::class)->log('api_key.revoked', null, ['id' => $id], [], $actorId);

        return $deleted;
    }

    private function mask(string $prefix): string
    {
        return $prefix.'••••••••••••••••';
    }

    /**
     * @return array<string, mixed>
     */
    public function webhooks(): array
    {
        return WebhookEndpoint::query()
            ->withCount('deliveries')
            ->orderByDesc('id')
            ->get()
            ->map(fn (WebhookEndpoint $endpoint): array => [
                'id' => (int) $endpoint->id,
                'name' => (string) $endpoint->name,
                'url' => (string) $endpoint->url,
                'host' => $this->hostOf((string) $endpoint->url),
                'events' => is_array($endpoint->events) ? $endpoint->events : [],
                'is_active' => (bool) $endpoint->is_active,
                'description' => (string) ($endpoint->description ?? ''),
                'secret_masked' => $this->maskSecret((string) $endpoint->secret),
                'failure_count' => (int) $endpoint->failure_count,
                'deliveries' => (int) $endpoint->deliveries_count,
                'last_triggered_at' => (string) ($endpoint->last_triggered_at?->format('Y-m-d H:i') ?? ''),
                'created_at' => (string) ($endpoint->created_at?->format('Y-m-d H:i') ?? ''),
            ])->all();
    }

    private function maskSecret(string $secret): string
    {
        if (strlen($secret) <= 8) {
            return '••••••••';
        }

        return substr($secret, 0, 4).'••••••••'.substr($secret, -4);
    }

    public function createWebhook(array $data, ?int $actorId): WebhookEndpoint
    {
        $endpoint = WebhookEndpoint::create([
            'name' => (string) $data['name'],
            'url' => (string) $data['url'],
            'secret' => 'whsec_'.Str::random(48),
            'events' => array_values((array) ($data['events'] ?? [])),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'description' => $data['description'] ?? null,
        ]);

        app(AuditLogger::class)->log('webhook.created', $endpoint, [], ['name' => $endpoint->name, 'url' => $endpoint->url], $actorId);

        return $endpoint;
    }

    public function updateWebhook(WebhookEndpoint $endpoint, array $data, ?int $actorId): WebhookEndpoint
    {
        $before = $endpoint->only(['name', 'url', 'is_active']);

        if (array_key_exists('name', $data)) {
            $endpoint->name = (string) $data['name'];
        }

        if (! empty($data['url'])) {
            $endpoint->url = (string) $data['url'];
        }

        if (array_key_exists('events', $data)) {
            $endpoint->events = array_values((array) $data['events']);
        }

        if (array_key_exists('is_active', $data)) {
            $endpoint->is_active = (bool) $data['is_active'];
        }

        if (array_key_exists('description', $data)) {
            $endpoint->description = $data['description'];
        }

        $endpoint->save();

        app(AuditLogger::class)->log('webhook.updated', $endpoint, $before, $endpoint->only(['name', 'url', 'is_active']), $actorId);

        return $endpoint;
    }

    public function rotateSecret(WebhookEndpoint $endpoint, ?int $actorId): string
    {
        $secret = 'whsec_'.Str::random(48);
        $endpoint->forceFill(['secret' => $secret])->save();

        app(AuditLogger::class)->log('webhook.secret_rotated', $endpoint, [], [], $actorId);

        return $secret;
    }

    /**
     * @return array<string, mixed>
     */
    public function deliveries(WebhookEndpoint $endpoint, int $page = 1, string $status = ''): array
    {
        $query = WebhookDelivery::query()->where('webhook_endpoint_id', $endpoint->id);

        if ($status !== '') {
            $query->where('status', $status);
        }

        $page = max(1, $page);
        $perPage = 20;
        $total = (int) (clone $query)->count();

        $rows = $query->orderByDesc('id')->forPage($page, $perPage)->get()
            ->map(fn (WebhookDelivery $delivery): array => [
                'id' => (int) $delivery->id,
                'event' => (string) $delivery->event,
                'event_id' => (string) $delivery->event_id,
                'attempt' => (int) $delivery->attempt,
                'max_attempts' => (int) $delivery->max_attempts,
                'status' => (string) $delivery->status,
                'response_status' => $delivery->response_status,
                'duration_ms' => $delivery->duration_ms,
                'payload' => Str::limit((string) json_encode($delivery->payload, JSON_UNESCAPED_UNICODE), 400),
                'response' => Str::limit((string) ($delivery->response_body ?? ''), 200),
                'delivered_at' => (string) ($delivery->delivered_at?->format('Y-m-d H:i') ?? ''),
                'next_retry_at' => (string) ($delivery->next_retry_at?->format('Y-m-d H:i') ?? ''),
                'created_at' => (string) ($delivery->created_at?->format('Y-m-d H:i') ?? ''),
            ])
            ->all();

        $summary = WebhookDelivery::query()
            ->where('webhook_endpoint_id', $endpoint->id)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        return [
            'endpoint' => [
                'id' => (int) $endpoint->id,
                'name' => (string) $endpoint->name,
                'url' => (string) $endpoint->url,
                'host' => $this->hostOf((string) $endpoint->url),
                'secret_masked' => $this->maskSecret((string) $endpoint->secret),
                'events' => is_array($endpoint->events) ? $endpoint->events : [],
            ],
            'rows' => $rows,
            'summary' => [
                'pending' => (int) ($summary['pending'] ?? 0),
                'delivered' => (int) ($summary['delivered'] ?? 0),
                'failed' => (int) ($summary['failed'] ?? 0),
                'exhausted' => (int) ($summary['exhausted'] ?? 0),
            ],
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * @return array{status: string, code: int|null, detail: string}
     */
    public function replay(WebhookEndpoint $endpoint, WebhookDelivery $delivery): array
    {
        if ((int) $delivery->webhook_endpoint_id !== (int) $endpoint->id) {
            abort(404, 'Delivery tidak dimiliki endpoint ini.');
        }

        $payload = is_array($delivery->payload) ? $delivery->payload : [];
        $signature = 't='.now()->getTimestamp().',v1='.hash_hmac('sha256', (string) json_encode($payload).$now->getTimestamp(), (string) $endpoint->secret);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Webhook-Event' => (string) $delivery->event,
                'X-Webhook-Id' => (string) $delivery->event_id,
                'X-Webhook-Signature' => $signature,
            ])
                ->timeout(10)
                ->connectTimeout(3)
                ->post((string) $endpoint->url, $payload);

            $delivery->forceFill([
                'attempt' => (int) $delivery->attempt + 1,
                'response_status' => $response->status(),
                'response_body' => Str::limit($response->body(), 1000),
                'duration_ms' => (int) $response->transferStats()['total_time'] ?? 0,
                'status' => $response->successful() ? 'delivered' : 'failed',
                'delivered_at' => $response->successful() ? now() : null,
            ])->save();

            $endpoint->forceFill([
                'last_triggered_at' => now(),
                'failure_count' => $response->successful() ? 0 : (int) $endpoint->failure_count + 1,
            ])->save();

            return [
                'status' => $response->successful() ? 'delivered' : 'failed',
                'code' => $response->status(),
                'detail' => 'Percobaan ulang '.((int) $delivery->attempt).' menghasilkan HTTP '.$response->status().'.',
            ];
        } catch (\Throwable) {
            $delivery->forceFill([
                'attempt' => (int) $delivery->attempt + 1,
                'status' => 'failed',
                'response_body' => 'Tidak dapat menghubungi endpoint.',
            ])->save();

            return ['status' => 'failed', 'code' => null, 'detail' => 'Endpoint tidak dapat dihubungi.'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function apiDocumentation(): array
    {
        $base = (string) config('app.url');

        return [
            'base_url' => rtrim($base, '/'),
            'version' => (string) config('app.version', 'v1'),
            'scopes' => self::SCOPES,
            'endpoints' => [
                ['method' => 'GET', 'path' => '/api/v1/products', 'description' => 'Daftar produk, paginasi dan filter.'],
                ['method' => 'GET', 'path' => '/api/v1/products/{slug}', 'description' => 'Detail satu produk beserta varian.'],
                ['method' => 'GET', 'path' => '/api/v1/orders', 'description' => 'Pesanan milik pelanggan pada kredensial ini.'],
                ['method' => 'GET', 'path' => '/api/v1/orders/{orderNumber}', 'description' => 'Detail pesanan beserta item dan status pengiriman.'],
                ['method' => 'GET', 'path' => '/api/v1/customers/me', 'description' => 'Profil pelanggan pemilik kredensial.'],
                ['method' => 'GET', 'path' => '/api/v1/payments/{paymentNumber}', 'description' => 'Status pembayaran.'],
            ],
            'events' => $this->eventCatalogue(),
            'notes' => [
                'Kirim API key pada header Authorization: Bearer <key>.',
                'Kunci hanya ditampilkan satu kali saat pembuatan; simpan di tempat aman.',
                'Webhook diverifikasi lewat header X-Webhook-Signature HMAC-SHA256.',
                'Batas laju: 120 permintaan per menit per kredensial.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function applicationLogs(int $page = 1, string $level = '', string $file = ''): array
    {
        $health = new SystemHealthService();
        $files = $health->logFiles();

        $selected = null;
        foreach ($files as $candidate) {
            if ($candidate['name'] === $file) {
                $selected = $candidate;
                break;
            }
        }

        $selected ??= $files[0] ?? null;
        $index = $selected === null ? 0 : (int) array_search($selected, $files, true);

        $page = max(1, min(max(1, (int) ceil(count($files) / 5)), (int) ceil(max(1, $index + 1) / 5)));
        $page = max(1, $page);

        $content = $selected !== null ? $health->readLog((string) $selected['name'], 400) : ['lines' => [], 'name' => null, 'bytes' => 0, 'modified' => ''];

        $lines = $content['lines'];
        if ($level !== '') {
            $pattern = '/^\[\d{4}-\d{2}-\d{2}[^\]]*\]\s+'.preg_quote($level, '/').'\./i';
            $lines = array_values(array_filter($lines, fn (string $line): bool => (bool) preg_match($pattern, $line)));
        }

        return [
            'files' => $files,
            'all_files' => $files,
            'selected' => $selected,
            'lines' => $lines,
            'levels' => ['ERROR', 'WARNING', 'INFO', 'DEBUG', 'CRITICAL', 'ALERT', 'EMERGENCY'],
            'pagination' => [
                'total' => count($files),
                'per_page' => 5,
                'current_page' => $page,
                'last_page' => (int) max(1, (int) ceil(max(1, count($files)) / 5)),
            ],
        ];
    }

    private function hostOf(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : '-';
    }
}
