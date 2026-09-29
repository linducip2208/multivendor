<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'uuid' => $this->uuid ?? null,
            'conversation_id' => $this->conversation_id === null ? null : (int) $this->conversation_id,
            'user_id' => $this->user_id === null ? null : (int) $this->user_id,
            'body' => $this->deleted_at !== null ? null : $this->body,
            'attachments' => $this->attachments ?? [],
            'is_deleted' => $this->deleted_at !== null,
            'is_flagged' => (bool) ($this->is_flagged ?? false),
            'read_at' => ApiResponse::iso($this->read_at),
            'author' => $this->whenLoaded('author', fn () => $this->author === null ? null : [
                'id' => (int) $this->author->id,
                'name' => $this->author->name ?? null,
                'avatar' => $this->author->avatar ?? null,
                'role' => $this->author->role ?? null,
            ]),
            'created_at' => ApiResponse::iso($this->created_at),
        ];
    }
}
