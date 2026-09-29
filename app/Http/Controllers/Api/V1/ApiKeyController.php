<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Services\Api\ApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApiKeyController extends ApiController
{
    public function index(Request $request, ApiKeyService $keys): JsonResponse
    {
        $rows = array_map(
            static fn (array $row): array => $row + ['revealed' => false],
            $keys->listFor((int) $request->user()->id)
        );

        return $this->ok($rows);
    }

    public function store(Request $request, ApiKeyService $keys): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'scopes' => 'nullable|array|max:8',
            'scopes.*' => ['string', Rule::in(['read', 'write', 'vendor', 'admin'])],
            'expires_in_days' => 'nullable|integer|min:1|max:3650',
        ]);

        $issued = $keys->create(
            $request->user(),
            $data['name'],
            $this->scopesFor($request, $data['scopes'] ?? ['read']),
            isset($data['expires_in_days']) ? now()->addDays((int) $data['expires_in_days']) : null
        );

        $this->markResource($request, 'api_key', $issued['id']);

        return $this->created(
            $issued + ['revealed' => true],
            'API key dibuat. Simpan sekarang, nilainya tidak akan ditampilkan lagi.'
        );
    }

    public function destroy(Request $request, ApiKeyService $keys, int $apiKey): JsonResponse
    {
        $this->abortUnlessOwned($keys->revoke($apiKey, (int) $request->user()->id));

        return $this->ok(['revoked' => true], 'API key dicabut.');
    }

    /**
     * A caller can never mint a scope above its own station: `admin` needs an
     * admin user, `vendor` needs a vendor or admin user. Anything else is
     * silently dropped so the issued key matches the grantable set.
     */
    private function scopesFor(Request $request, array $requested): array
    {
        $user = $request->user();
        $allowed = ['read', 'write'];

        if (method_exists($user, 'isVendor') && $user->isVendor()) {
            $allowed[] = 'vendor';
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            $allowed[] = 'vendor';
            $allowed[] = 'admin';
        }

        $filtered = array_values(array_intersect(
            array_map(static fn ($scope): string => strtolower(trim((string) $scope)), $requested),
            $allowed
        ));

        return $filtered === [] ? ['read'] : array_values(array_unique($filtered));
    }
}
