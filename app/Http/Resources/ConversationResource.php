<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'uuid' => $this->uuid ?? null,
            'type' => $this->type ?? null,
            'subject' => $this->subject ?? null,
            'status' => $this->status ?? null,
            'priority' => $this->priority ?? null,
            'shop_id' => $this->shop_id === null ? null : (int) $this->shop_id,
            'order_id' => $this->order_id === null ? null : (int) $this->order_id,
            'unread_count' => $this->when(isset($this->unread_count), (int) $this->unread_count),
            'messages_count' => $this->when(isset($this->messages_count), (int) $this->messages_count),
            'last_message' => $this->whenLoaded('messages', fn () => $this->messages->last() === null ? null : [
                'id' => (int) $this->messages->last()->id,
                'body' => $this->messages->last()->deleted_at === null ? $this->messages->last()->body : null,
                'created_at' => ApiResponse::iso($this->messages->last()->created_at),
            ]),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'shop' => new ShopPublicResource($this->whenLoaded('shop')),
            'order' => $this->whenLoaded('order', fn () => $this->order === null ? null : [
                'id' => (int) $this->order->id,
                'order_number' => $this->order->order_number,
                'status' => $this->order->order_status,
            ]),
            'first_reply_at' => ApiResponse::iso($this->first_reply_at),
            'last_message_at' => ApiResponse::iso($this->last_message_at),
            'resolved_at' => ApiResponse::iso($this->resolved_at),
            'created_at' => ApiResponse::iso($this->created_at),
        ];
    }
}
