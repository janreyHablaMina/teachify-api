<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeacherNotification;
use App\Services\TeacherNotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly TeacherNotificationService $notificationService)
    {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $paginator = $this->notificationService->syncAndFetch($request->user(), $perPage);

        $items = collect($paginator->items())->map(function (TeacherNotification $item) {
            return [
                'id' => (string) $item->id,
                'title' => $item->title,
                'message' => $item->message,
                'category' => $item->category,
                'type' => $item->event_type,
                'severity' => $item->severity,
                'created_at' => optional($item->occurred_at)->toISOString(),
                'read' => (bool) $item->is_read,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function markAsRead(Request $request, TeacherNotification $notification)
    {
        $updated = $this->notificationService->markAsRead($request->user(), $notification);

        return response()->json([
            'message' => 'Notification marked as read.',
            'notification' => [
                'id' => (string) $updated->id,
                'read' => (bool) $updated->is_read,
                'read_at' => optional($updated->read_at)->toISOString(),
            ],
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $updatedCount = $this->notificationService->markAllAsRead($request->user());

        return response()->json([
            'message' => 'All notifications marked as read.',
            'updated_count' => $updatedCount,
        ]);
    }

    public function destroy(Request $request, TeacherNotification $notification)
    {
        $this->notificationService->delete($request->user(), $notification);

        return response()->json([
            'message' => 'Notification deleted successfully.',
        ]);
    }
}

