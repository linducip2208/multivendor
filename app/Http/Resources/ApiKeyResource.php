<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\Api\ApiKeyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiKeyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $row = (array) $this->resource;

        if (array_key_exists('key', $row)) {
            return $row + ['revealed' => true];
        }

        return app(ApiKeyService::class)->present((object) $row) + ['revealed' => false];
    }
}
