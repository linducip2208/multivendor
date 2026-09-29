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
            $data['scopes'] ?? ['read'],
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
}
