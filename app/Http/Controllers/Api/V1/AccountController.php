<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\CustomerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        return $this->ok(new CustomerResource($request->user()->load('wallet')));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|nullable|string|max:20',
            'avatar' => 'sometimes|nullable|string|max:255',
        ]);

        $request->user()->fill($data)->save();

        return $this->ok(new CustomerResource($request->user()->fresh('wallet')), 'Profil diperbarui');
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->markResource($request, 'user', (int) $user->id);

        if ((string) $user->status === 'deleted') {
            return $this->ok(['status' => 'deleted'], 'Akun sudah tidak aktif.');
        }

        $user->forceFill(['status' => 'deleted'])->save();

        return $this->ok(['status' => 'deleted'], 'Akun dinonaktifkan.');
    }
}
