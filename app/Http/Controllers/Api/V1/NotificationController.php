<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Notification\ListNotificationsRequest;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(ListNotificationsRequest $request): JsonResponse
    {
        $status = $request->validated('status', 'all');

        $notifications = $request->user()
            ->notifications()
            ->when(
                $status === 'read',
                fn ($query) => $query->whereNotNull('read_at'),
            )
            ->when(
                $status === 'unread',
                fn ($query) => $query->whereNull('read_at'),
            )
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return $this->successResponse(
            [
                'notifications' => NotificationResource::collection($notifications),
                'unread_count' => $request->user()->unreadNotifications()->count(),
                'pagination' => $this->pagination($notifications),
            ],
            'Notifications fetched successfully.',
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->successResponse(
            ['unread_count' => $request->user()->unreadNotifications()->count()],
            'Unread notifications count fetched successfully.',
        );
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $notification = $this->notificationForUser($request, $notification);
        $notification->markAsRead();

        return $this->successResponse(
            new NotificationResource($notification->refresh()),
            'Notification marked as read successfully.',
        );
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        return $this->successResponse(
            ['unread_count' => 0],
            'All notifications marked as read successfully.',
        );
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $this->notificationForUser($request, $notification)->delete();

        return $this->successResponse(
            null,
            'Notification deleted successfully.',
        );
    }

    private function notificationForUser(Request $request, string $id): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $request->user()
            ->notifications()
            ->whereKey($id)
            ->firstOrFail();

        return $notification;
    }
}
