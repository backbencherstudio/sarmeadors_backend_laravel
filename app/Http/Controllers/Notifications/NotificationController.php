<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

abstract class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = $request->user()
                ->notifications()
                ->latest();

            if ($request->query('filter') === 'unread') {
                $query->whereNull('read_at');
            }

            $notifications = $query->paginate($request->integer('per_page', 10));
            $notifications->getCollection()->transform(fn (DatabaseNotification $notification): array => $this->formatNotification($notification));

            return $this->sendResponse([
                'unread_count' => $request->user()->unreadNotifications()->count(),
                'notifications' => $notifications,
            ], 'Notifications retrieved successfully.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        try {
            $notification = $request->user()
                ->notifications()
                ->whereKey($id)
                ->first();

            if (! $notification) {
                return $this->sendError('Notification not found.', [], 404);
            }

            $notification->markAsRead();

            return $this->sendResponse(
                $this->formatNotification($notification->fresh()),
                'Notification marked as read.',
                200
            );
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function markAllRead(Request $request): JsonResponse
    {
        try {
            $marked = $request->user()->unreadNotifications()->count();

            $request->user()
                ->unreadNotifications()
                ->update(['read_at' => now()]);

            return $this->sendResponse([
                'marked' => $marked,
            ], 'All notifications marked as read.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $deleted = $request->user()
                ->notifications()
                ->whereKey($id)
                ->delete();

            if (! $deleted) {
                return $this->sendError('Notification not found.', [], 404);
            }

            return $this->sendResponse([], 'Notification deleted.', 200);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', $e->getMessage(), 500);
        }
    }

    private function formatNotification(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? Str::of(class_basename($notification->type))->snake()->toString(),
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? $data['message'] ?? null,
            'action_url' => $data['action_url'] ?? null,
            'meta' => $data['meta'] ?? [],
            'read_at' => $notification->read_at?->toDateTimeString(),
            'created_at' => $notification->created_at?->toDateTimeString(),
            'time_ago' => $notification->created_at?->shortRelativeToNowDiffForHumans(),
            'group' => $this->resolveGroup($notification),
            'is_read' => filled($notification->read_at),
        ];
    }

    /**
     * Date bucket label used by the notification panel (Today / Yesterday / date).
     */
    private function resolveGroup(DatabaseNotification $notification): ?string
    {
        $createdAt = $notification->created_at;

        if (! $createdAt) {
            return null;
        }

        if ($createdAt->isToday()) {
            return 'Today';
        }

        if ($createdAt->isYesterday()) {
            return 'Yesterday';
        }

        return $createdAt->format('M d, Y');
    }
}
