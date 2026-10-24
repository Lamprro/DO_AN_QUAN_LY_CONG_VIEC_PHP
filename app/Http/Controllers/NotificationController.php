<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationService $service)
    {
        $data = $request->validate(['filter' => ['sometimes', 'in:all,unread,read'], 'type' => ['nullable', Rule::in(['task.created', 'task.updated', 'task.status_changed', 'task.comment_added', 'task.reminder_due', 'task.reminder_review', 'task.mentioned', 'calendar.proposed', 'calendar.responded'])]]);
        $notifications = $service->visible($request->user())->when(($data['filter'] ?? 'all') === 'unread', fn ($q) => $q->whereNull('read_at'))->when(($data['filter'] ?? 'all') === 'read', fn ($q) => $q->whereNotNull('read_at'))->when($data['type'] ?? null, fn ($q, $type) => $q->where('type', $type))->with(['workspace', 'project', 'task'])->orderByDesc('created_at')->orderByDesc('id')->paginate(20)->withQueryString();

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
