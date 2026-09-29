<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Services\Api\ApiCatalog;
use App\Services\Api\ApiFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatController extends ApiController
{
    public function index(Request $request, ApiFilter $filter): JsonResponse
    {
        $paginator = ApiCatalog::conversations()->paginate($this->scoped($request), $request);

        return $this->paged(
            $paginator,
            ConversationResource::collection($paginator->getCollection())->resolve($request),
            'OK',
            $filter,
            $request
        );
    }

    public function show(Request $request, int $conversation): JsonResponse
    {
        $model = $this->owned($request, $conversation);

        return $this->ok(new ConversationResource($model->load(['shop', 'order', 'messages'])));
    }

    public function messages(Request $request, int $conversation, ApiFilter $filter): JsonResponse
    {
        $model = $this->owned($request, $conversation);
        $paginator = ApiCatalog::messages()->paginate(
            $model->messages()->whereNull('deleted_at')->with('author'),
            $request
        );

        $this->markReadInternal($request, $model);

        return $this->paged(
            $paginator,
            MessageResource::collection($paginator->getCollection())->resolve($request),
            'OK',
            $filter,
            $request
        );
    }

    public function storeMessage(Request $request, int $conversation): JsonResponse
    {
        $model = $this->owned($request, $conversation);
        $data = $request->validate([
            'body' => 'required|string|max:5000',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'string|max:255',
        ]);

        $message = DB::transaction(function () use ($model, $request, $data): Message {
            $message = $model->messages()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $request->user()->id,
                'body' => $data['body'],
                'attachments' => $data['attachments'] ?? [],
                'is_internal_note' => false,
            ]);

            $model->forceFill(['last_message_at' => now()])->save();

            return $message;
        });

        $this->markResource($request, 'message', (int) $message->id);

        return $this->created(new MessageResource($message->load('author')), 'Pesan terkirim');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => 'required|string|max:190',
            'shop_id' => 'nullable|integer|exists:shops,id',
            'order_id' => 'nullable|integer',
            'type' => 'nullable|in:support,order,product',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'body' => 'required|string|max:5000',
        ]);

        if (! empty($data['order_id'])) {
            $this->abortUnlessOwned(
                \App\Models\Order::where('customer_id', $request->user()->id)->whereKey($data['order_id'])->exists(),
                'order_not_found'
            );
        }

        $conversation = DB::transaction(function () use ($request, $data): Conversation {
            $conversation = Conversation::create([
                'uuid' => (string) Str::uuid(),
                'type' => $data['type'] ?? 'support',
                'subject' => $data['subject'],
                'shop_id' => $data['shop_id'] ?? null,
                'order_id' => $data['order_id'] ?? null,
                'status' => 'open',
                'priority' => $data['priority'] ?? 'normal',
                'last_message_at' => now(),
            ]);

            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $request->user()->id,
                'role' => 'customer',
                'last_read_at' => now(),
            ]);

            $conversation->messages()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $request->user()->id,
                'body' => $data['body'],
                'is_internal_note' => false,
            ]);

            return $conversation;
        });

        $this->markResource($request, 'conversation', (int) $conversation->id);

        return $this->created(
            new ConversationResource($conversation->load(['shop', 'messages.author'])),
            'Percakapan dibuat'
        );
    }

    public function markRead(Request $request, int $conversation): JsonResponse
    {
        $this->owned($request, $conversation);

        ConversationParticipant::where('conversation_id', $conversation)
            ->where('user_id', $request->user()->id)
            ->update(['last_read_at' => now(), 'unread_count' => 0]);

        return $this->ok(null, 'Percakapan ditandai dibaca');
    }

    private function markReadInternal(Request $request, Conversation $conversation): void
    {
        ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $request->user()->id)
            ->update(['last_read_at' => now(), 'unread_count' => 0]);
    }

    private function scoped(Request $request): Builder
    {
        return Conversation::whereIn('id', ConversationParticipant::query()
            ->select('conversation_id')
            ->where('user_id', $request->user()->id));
    }

    private function owned(Request $request, int $conversation): Conversation
    {
        $model = $this->scoped($request)->whereKey($conversation)->first();

        $this->abortUnlessOwned($model !== null, 'conversation_not_found');

        return $model;
    }
}
