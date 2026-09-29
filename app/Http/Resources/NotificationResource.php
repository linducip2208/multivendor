<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'type' => $this->type,
            'title' => $this->data['title'] ?? null,
            'body' => $this->data['body'] ?? ($this->data['message'] ?? null),
            'data' => $this->data ?? [],
            'read' => $this->read_at !== null,
            'read_at' => ApiResponse::iso($this->read_at),
            'created_at' => ApiResponse::iso($this->created_at),
        ];
    }
}
