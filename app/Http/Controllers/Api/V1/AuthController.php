<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\CustomerResource;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Api\ApiPrincipal;
use App\Services\Api\PersonalAccessTokenIssuer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends ApiController
{
    public function login(Request $request, PersonalAccessTokenIssuer $tokens): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:120',
            'scopes' => 'nullable|array|max:8',
            'scopes.*' => 'string|in:read,write',
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user === null || ! Hash::check($data['password'], (string) $user->password)) {
            return ApiResponse::error('invalid_credentials', 'Email atau password salah.', 401);
        }

        if ((string) $user->status !== 'active') {
            return ApiResponse::error('account_inactive', 'Akun tidak aktif.', 403);
        }

        $issued = $tokens->issue(
            $user,
            $data['device_name'] ?? 'api-client',
            $this->scopesFor($user, $data['scopes'] ?? ['read', 'write'])
        );

        $this->markResource($request, 'personal_access_token', $issued['id']);

        return $this->ok([
            'token_type' => 'Bearer',
            'access_token' => $issued['token'],
            'token' => $issued['token'],
            'scopes' => $issued['scopes'],
            'expires_at' => $issued['expires_at'],
            'user' => new CustomerResource($user->load('wallet')),
        ], 'Login berhasil');
    }

    public function register(Request $request, PersonalAccessTokenIssuer $tokens): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => 'required|string|min:8|max:72',
            'phone' => 'nullable|string|max:20',
            'referral_code' => 'nullable|string|max:32',
            'device_name' => 'nullable|string|max:120',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'role' => 'customer',
            'status' => 'active',
            'referral_code' => $this->uniqueReferralCode(),
            'referred_by' => $this->referrerId($data['referral_code'] ?? null),
        ]);

        Wallet::firstOrCreate(['user_id' => $user->id], ['balance' => 0]);

        $issued = $tokens->issue($user, $data['device_name'] ?? 'api-client', ['read', 'write']);
        $this->markResource($request, 'user', (int) $user->id);

        return $this->created([
            'token_type' => 'Bearer',
            'access_token' => $issued['token'],
            'token' => $issued['token'],
            'scopes' => $issued['scopes'],
            'expires_at' => $issued['expires_at'],
            'user' => new CustomerResource($user->load('wallet')),
        ], 'Registrasi berhasil');
    }

    public function logout(Request $request, PersonalAccessTokenIssuer $tokens): JsonResponse
    {
        $principal = $request->attributes->get('api_principal');

        if ($principal instanceof ApiPrincipal && $principal->isToken()) {
            $tokens->revoke($request->user(), (string) $principal->credentialId);

            return $this->ok(['revoked' => true], 'Token dicabut.');
        }

        return $this->ok(['revoked' => false], 'Kredensial API key tidak memiliki sesi token untuk dicabut.');
    }

    public function logoutAll(Request $request, PersonalAccessTokenIssuer $tokens): JsonResponse
    {
        return $this->ok(['revoked' => $tokens->revokeAll($request->user())], 'Semua token dicabut.');
    }

    public function tokens(Request $request, PersonalAccessTokenIssuer $tokens): JsonResponse
    {
        return $this->ok($tokens->tokensFor($request->user()));
    }

    public function revokeToken(Request $request, PersonalAccessTokenIssuer $tokens, string $token): JsonResponse
    {
        $this->abortUnlessOwned($tokens->revoke($request->user(), $token));

        return $this->ok(['revoked' => true], 'Token dicabut.');
    }

    private function scopesFor(User $user, array $requested): array
    {
        $requested = array_values(array_filter(
            $requested,
            static fn ($scope): bool => in_array($scope, ['read', 'write'], true)
        ));

        if ($requested === []) {
            $requested = ['read'];
        }

        if ($user->isAdmin()) {
            return ['read', 'write', 'admin'];
        }

        if ($user->isVendor()) {
            return array_values(array_unique([...$requested, 'vendor']));
        }

        return $requested;
    }

    private function uniqueReferralCode(): string
    {
        do {
            $code = Str::random(8);
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }

    private function referrerId(?string $code): ?int
    {
        $code = $code === null ? '' : trim($code);

        if ($code === '') {
            return null;
        }

        $referrer = User::where('referral_code', $code)->first();

        return $referrer === null ? null : (int) $referrer->id;
    }
}
