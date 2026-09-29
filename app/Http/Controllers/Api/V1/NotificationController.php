<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\User;
use App\Services\Api\ApiCatalog;
use App\Services\Api\ApiFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $filter = ApiCatalog::notifications();
        $paginator = $filter->paginate($this->scoped($request), $request);

        return $this->paged(
            $paginator,
            NotificationResource::collection($paginator->getCollection())->resolve($request),
            'OK',
            $filter,
            $request
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->ok(['unread' => $this->scoped($request)->whereNull('read_at')->count()]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $model = $this->owned($request, $notification);
        $model->markAsRead();

        return $this->ok(new NotificationResource($model->fresh()));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $this->scoped($request)->whereNull('read_at')->update(['read_at' => now()]);

        return $this->ok(['updated' => $count], 'Semua notifikasi ditandai dibaca');
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $this->owned($request, $notification)->delete();

        return $this->ok(null, 'Notifikasi dihapus');
    }

    private function scoped(Request $request)
    {
        return Notification::where('notifiable_type', User::class)
            ->where('notifiable_id', $request->user()->id);
    }

    private function owned(Request $request, string $notification): Notification
    {
        $model = $this->scoped($request)
            ->where('id', $notification)
            ->first();

        $this->abortUnlessOwned($model !== null, 'notification_not_found');

        return $model;
    }
}
