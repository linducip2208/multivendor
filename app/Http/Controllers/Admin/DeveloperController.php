<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Backoffice\DeveloperService;
use App\Services\Backoffice\SystemHealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeveloperController extends Controller
{
    public function __construct(
        private readonly DeveloperService $developer,
        private readonly SystemHealthService $health,
    ) {}

    public function index(): View
    {
        return view('admin.developer.index', [
            'documentation' => $this->developer->apiDocumentation(),
            'apiKeys' => $this->developer->apiKeys(),
            'scopes' => DeveloperService::SCOPES,
        ]);
    }

    public function apiKeys(): View
    {
        return view('admin.developer.api-keys', [
            'rows' => $this->developer->apiKeys(),
            'scopes' => DeveloperService::SCOPES,
        ]);
    }

    public function storeApiKey(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', Rule::in(array_keys(DeveloperService::SCOPES))],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $result = $this->developer->createApiKey(
            (string) $validated['name'],
            array_values((array) ($validated['scopes'] ?? [])),
            isset($validated['expires_in_days']) ? (int) $validated['expires_in_days'] : null,
            auth('admin')->id(),
        );

        return back()
            ->with('success', 'Kunci API dibuat. Salin sekarang — nilai penuh hanya ditampilkan sekali.')
            ->with('api_key_plaintext', $result['plaintext'])
            ->with('api_key_name', $result['record']['name']);
    }

    public function destroyApiKey(Request $request, int $id): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $deleted = $this->developer->revokeApiKey($id, auth('admin')->id());

        return back()->with(
            $deleted ? 'success' : 'error',
            $deleted ? 'Kunci API dicabut dan tidak dapat dipakai lagi.' : 'Kunci API tidak ditemukan.',
        );
    }

    public function webhooks(): View
    {
        return view('admin.developer.webhooks', [
            'rows' => $this->developer->webhooks(),
            'events' => $this->developer->eventCatalogue(),
        ]);
    }

    public function storeWebhook(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1', 'max:40'],
            'events.*' => ['string', Rule::in($this->developer->eventNames())],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $endpoint = $this->developer->createWebhook($validated, auth('admin')->id());

        return back()->with('success', 'Endpoint webhook "'.$endpoint->name.'" dibuat dengan secret yang di-generate server.');
    }

    public function updateWebhook(Request $request, WebhookEndpoint $webhook): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1', 'max:40'],
            'events.*' => ['string', Rule::in($this->developer->eventNames())],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $this->developer->updateWebhook($webhook, $validated, auth('admin')->id());

        return back()->with('success', 'Endpoint webhook diperbarui.');
    }

    public function rotateWebhookSecret(WebhookEndpoint $webhook): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $this->developer->rotateSecret($webhook, auth('admin')->id());

        return back()->with('success', 'Secret webhook dirotasi. Endpoint lama yang masih menyimpan secret lama harus diperbarui.');
    }

    public function destroyWebhook(WebhookEndpoint $webhook): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $name = (string) $webhook->name;
        $webhook->delete();

        return back()->with('success', 'Endpoint webhook "'.$name.'" dihapus.');
    }

    public function webhookDeliveries(Request $request, WebhookEndpoint $webhook): View
    {
        return view('admin.developer.webhook-deliveries', $this->developer->deliveries(
            $webhook,
            (int) $request->query('page', 1),
            (string) $request->query('status', ''),
        ));
    }

    public function replayDelivery(Request $request, WebhookEndpoint $webhook, WebhookDelivery $delivery): RedirectResponse
    {
        $result = $this->developer->replay($webhook, $delivery);

        return back()->with($result['status'] === 'delivered' ? 'success' : 'error', $result['detail']);
    }

    public function events(): View
    {
        $catalogue = $this->developer->eventCatalogue();

        $subscriptions = [];
        foreach (WebhookEndpoint::query()->get(['name', 'events', 'is_active']) as $endpoint) {
            foreach ((array) $endpoint->events as $event) {
                $subscriptions[(string) $event][] = [
                    'name' => (string) $endpoint->name,
                    'active' => (bool) $endpoint->is_active,
                ];
            }
        }

        $rows = array_map(function (array $event) use ($subscriptions): array {
            $event['subscribers'] = $subscriptions[$event['event']] ?? [];

            return $event;
        }, $catalogue);

        usort($rows, fn (array $a, array $b): int => [$a['group'], $a['event']] <=> [$b['group'], $b['event']]);

        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['group']][] = $row;
        }

        return view('admin.developer.events', [
            'groups' => $groups,
            'total_events' => count($rows),
            'subscribed_events' => count($subscriptions),
        ]);
    }

    public function logs(Request $request): View
    {
        return view('admin.developer.logs', $this->developer->applicationLogs(
            (int) $request->query('page', 1),
            (string) $request->query('level', ''),
            (string) $request->query('file', ''),
        ));
    }

    private function ensureSuperAdmin(): void
    {
        abort_unless(auth('admin')->user()?->isSuperAdmin(), 403, 'Hanya super admin yang dapat mengelola kredensial dan secret.');
    }
}
