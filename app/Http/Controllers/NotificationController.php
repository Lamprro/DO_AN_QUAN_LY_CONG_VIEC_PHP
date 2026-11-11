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
        $service->read($request->user(), $notification, (bool) ($data['unread'] ?? false));

        return back()->with('success', 'Đã cập nhật trạng thái đọc.');
    }

    public function readAll(Request $request, NotificationService $service)
    {
        $service->read($request->user());

        return back()->with('success', 'Đã đánh dấu các thông báo được phép xem là đã đọc.');
    }
}
