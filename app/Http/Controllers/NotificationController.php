<?php

namespace App\Http\Controllers;

use App\Services\Notification\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationService $service)
    {
        $data = $request->validate([
            'filter' => ['sometimes', 'in:all,unread,read'],
            'per_page' => ['nullable', Rule::in([10, 50, 100, 500])],
            'type' => [
                'nullable',
                Rule::in([
                    'task.created',
                    'task.updated',
                    'task.status_changed',
                    'task.comment_added',
                    'task.reminder_due',
                    'task.reminder_review',
                    'task.mentioned',
                    'calendar.proposed',
                    'calendar.responded',
                ]),
            ],
        ]);
        $notifications = $service->inbox($request->user(), $data);

        return view('notifications.index', compact('notifications'));
    }

    public function read(Request $request, NotificationService $service, string $notification)
    {
        $data = $request->validate(['unread' => ['sometimes', 'boolean']]);
        $stored = $service->read($request->user(), $notification, (bool) ($data['unread'] ?? false));
        $message = 'Đã cập nhật trạng thái đọc.';

        if ($request->wantsJson()) {
            // The page renders from this state, not from what the button assumed.
            return response()->json([
                'success' => true,
                'message' => $message,
                'id' => $stored->id,
                'read' => $stored->read_at !== null,
                'read_at' => $stored->read_at?->toIso8601String(),
                'unread_count' => $service->unreadCount($request->user()),
            ]);
        }

        return back()->with('success', $message);
    }

    public function readAll(Request $request, NotificationService $service)
    {
        $service->read($request->user());
        $message = 'Đã đánh dấu các thông báo được phép xem là đã đọc.';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'unread_count' => $service->unreadCount($request->user()),
            ]);
        }

        return back()->with('success', $message);
    }
}
