<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'customer_id' => $this->customer_id === null ? null : (int) $this->customer_id,
            'subject' => $this->subject,
            'type' => $this->type ?? null,
            'priority' => $this->priority ?? null,
            'description' => $this->description ?? null,
            'status' => $this->status ?? null,
            'assigned_to' => $this->assigned_to === null ? null : (int) $this->assigned_to,
            'replies' => $this->whenLoaded('replies', fn () => $this->replies->map(fn ($reply): array => [
                'id' => (int) $reply->id,
                'user_id' => $reply->user_id === null ? null : (int) $reply->user_id,
                'body' => $reply->message ?? null,
                'created_at' => ApiResponse::iso($reply->created_at),
            ])->all()),
            'resolved_at' => ApiResponse::iso($this->resolved_at),
            'created_at' => ApiResponse::iso($this->created_at),
            'updated_at' => ApiResponse::iso($this->updated_at),
        ];
    }
}
