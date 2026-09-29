<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Vendor\VendorChatService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private readonly VendorChatService $chat) {}

    public function inbox(Request $request): View
    {
        $shop = auth('vendor')->user()->shop;

        return view('vendor.chat.inbox', [
            'conversations' => $this->chat->inbox(),
            'quickReplies' => $this->chat->quickReplies(),
            'customers' => User::query()
                ->where('role', 'customer')
                ->whereHas('orders', fn ($query) => $query->where('shop_id', (int) $shop?->id))
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function messages(Request $request, int $conversation): View
    {
        $thread = $this->chat->find($conversation);
        $this->chat->markRead($thread);

        return view('vendor.chat.messages', [
            'conversation' => $thread,
            'participants' => $this->chat->customers($thread),
            'messages' => $thread->messages()->with('author:id,name,role')->orderBy('created_at')->get(),
            'sla' => $this->chat->slaStatus($thread),
            'quickReplies' => $this->chat->quickReplies(),
        ]);
    }

    public function messagesByCustomer(Request $request, User $user): View
    {
        $thread = $this->chat->withCustomer((int) $user->getKey());

        return redirect()->route('vendor.chat.messages', $thread->getKey());
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'body' => ['required', 'string', 'min:1', 'max:5000'],
            'order_id' => ['nullable', 'integer'],
        ], [
            'body.required' => 'Pesan tidak boleh kosong.',
        ]);

        $conversation = $this->resolveConversation($validated);

        $message = $this->chat->store($conversation, $validated);

        if ($request->boolean('redirect')) {
            return response()->json([
                'success' => true,
                'redirect' => route('vendor.chat.messages', $conversation->getKey()),
            ], 201);
        }

        return response()->json([
            'success' => true,
            'message' => [
                'id' => (int) $message->getKey(),
                'conversation_id' => (int) $conversation->getKey(),
                'body' => (string) $message->body,
                'author' => auth('vendor')->user()->name,
                'created_at' => $message->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    private function resolveConversation(array $validated): Conversation
    {
        if (! empty($validated['conversation_id'])) {
            return $this->chat->find((int) $validated['conversation_id']);
        }

        if (empty($validated['user_id'])) {
            abort(422, 'Percakapan atau pelanggan harus ditentukan.');
        }

        return $this->chat->withCustomer((int) $validated['user_id']);
    }
}
