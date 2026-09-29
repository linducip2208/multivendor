<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Services\Api\ApiCatalog;
use App\Services\Api\ApiFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportController extends ApiController
{
    public function index(Request $request, ApiFilter $filter): JsonResponse
    {
        $paginator = ApiCatalog::supportTickets()->paginate(
            SupportTicket::where('customer_id', $request->user()->id),
            $request
        );

        return $this->paged(
            $paginator,
            SupportTicketResource::collection($paginator->getCollection())->resolve($request),
            'OK',
            $filter,
            $request
        );
    }

    public function show(Request $request, int $ticket): JsonResponse
    {
        return $this->ok(new SupportTicketResource($this->owned($request, $ticket)->load('replies')));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => 'required|string|max:190',
            'type' => 'nullable|in:order,payment,product,account,other',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'description' => 'required|string|max:5000',
        ]);

        $ticket = SupportTicket::create($data + [
            'customer_id' => $request->user()->id,
            'status' => 'open',
        ]);

        $this->markResource($request, 'support_ticket', (int) $ticket->id);

        return $this->created(new SupportTicketResource($ticket), 'Tiket dibuat');
    }

    public function reply(Request $request, int $ticket): JsonResponse
    {
        $model = $this->owned($request, $ticket);
        $data = $request->validate(['body' => 'required|string|max:5000']);

        $reply = $model->replies()->create([
            'user_id' => $request->user()->id,
            'message' => $data['body'],
        ]);

        $this->markResource($request, 'support_ticket_reply', (int) $reply->id);

        return $this->created(new SupportTicketResource($model->fresh('replies')), 'Balasan terkirim');
    }

    public function close(Request $request, int $ticket): JsonResponse
    {
        $model = $this->owned($request, $ticket);
        $model->forceFill(['status' => 'closed', 'resolved_at' => now()])->save();

        return $this->ok(new SupportTicketResource($model->fresh('replies')), 'Tiket ditutup');
    }

    private function owned(Request $request, int $ticket): SupportTicket
    {
        $model = SupportTicket::where('customer_id', $request->user()->id)->whereKey($ticket)->first();

        $this->abortUnlessOwned($model !== null, 'support_ticket_not_found');

        return $model;
    }
}
