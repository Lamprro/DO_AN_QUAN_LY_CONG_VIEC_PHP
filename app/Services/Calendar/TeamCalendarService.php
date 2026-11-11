<?php

namespace App\Services\Calendar;

use App\Authorization\CalendarAccess;
use App\Authorization\TaskAccess;
use App\Enums\CalendarEventStatus;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Notification\NotificationService;
use App\Support\LocalTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Team Calendar proposals. A proposal is a calendar_events row owned by the recipient
 * (user_id) with proposed_by set. Lifecycle:
 *   proposed --accept (recipient)--> planned --cancel (recipient)--> cancelled
 *   proposed --decline (recipient)--> declined
 *   proposed --cancel (proposer)---> withdrawn
 */
class TeamCalendarService
{
    public function __construct(private CalendarAccess $calendars, private TaskAccess $tasks, private CalendarService $calendar, private NotificationService $notifications) {}

    public function find(Workspace $workspace, string $id): CalendarEvent
    {
        return CalendarEvent::proposals()->where('workspace_id', $workspace->id)->findOrFail($id);
    }

    public function propose(User $actor, Workspace $workspace, array $data): CalendarEvent
    {
        $data = Validator::make($data, [
            'project_id' => 'nullable|uuid',
            'recipient_id' => 'required|uuid',
            'task_id' => 'nullable|uuid',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:4000',
            'event_type' => ['required', Rule::in(['MEETING', 'TASK_BLOCK'])],
            'visibility' => ['required', Rule::in(['private', 'project', 'workspace'])],
            'timezone' => 'required|timezone',
            'starts_at' => 'required|date_format:Y-m-d\TH:i',
            'ends_at' => 'required|date_format:Y-m-d\TH:i',
        ])->validate();

        return DB::transaction(function () use ($actor, $workspace, $data) {
            $workspace = Workspace::lockForUpdate()->findOrFail($workspace->id);
            $project = empty($data['project_id']) ? null : $workspace->projects()->findOrFail($data['project_id']);
            abort_unless($this->calendars->scope($actor, $workspace, $project, 'calendar.create') && $this->tasks->projectWritable($project), 403);
            $recipient = User::findOrFail($data['recipient_id']);
            abort_unless($recipient->id !== $actor->id && $this->calendars->scope($recipient, $workspace, $project, 'calendar.create'), 403);
            if ($data['visibility'] === 'project' && ! $project || $data['visibility'] === 'workspace' && $project) {
                throw ValidationException::withMessages(['visibility' => 'Chọn phạm vi chia sẻ phù hợp với project.']);
            }
            if ($data['event_type'] === 'TASK_BLOCK') {
                abort_unless($this->tasks->scope($actor, $workspace, $project, 'task.assign'), 403);
                $task = $workspace->tasks()->where('project_id', $project?->id)->findOrFail($data['task_id'] ?? null);
                if (Gate::forUser($actor)->denies('view', $task) || Gate::forUser($recipient)->denies('update', $task) || $task->assignee_id !== $recipient->id || $task->task_type->value !== 'WORK' || in_array($task->status->value, ['DONE', 'CANCELLED'])) {
                    throw ValidationException::withMessages(['task_id' => 'Chọn WORK đang mở giao cho người nhận và có quyền cập nhật.']);
                }
            } elseif (! empty($data['task_id'])) {
                throw ValidationException::withMessages(['task_id' => 'Cuộc họp không gắn task; khung làm việc dùng TASK_BLOCK.']);
            }
            $start = LocalTime::parse($data['starts_at'], $data['timezone'], false);
            $end = LocalTime::parse($data['ends_at'], $data['timezone'], false);
            if ($start->isPast() || $end->lte($start) || $end->gt($start->addDay())) {
                throw ValidationException::withMessages(['starts_at' => 'Chọn giờ tương lai, kết thúc sau bắt đầu và tối đa24 giờ.']);
            }
            $proposal = CalendarEvent::create([
                'workspace_id' => $workspace->id,
                'project_id' => $project?->id,
                'task_id' => $data['task_id'] ?? null,
                'user_id' => $recipient->id,
                'proposed_by' => $actor->id,
                'event_type' => $data['event_type'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'starts_at' => $start,
                'ends_at' => $end,
                'timezone' => $data['timezone'],
                'all_day' => false,
                'is_busy' => true,
                'visibility' => $data['visibility'],
                'status' => CalendarEventStatus::PROPOSED,
                'participants' => [],
                'source' => 'manual',
            ]);
            $this->notifications->direct($recipient, $workspace, $project, 'calendar.proposed', 'Có đề xuất lịch cần bạn xác nhận', $proposal->title, $this->notificationKey($proposal), $proposal->task);

            return $proposal;
        }, 3);
    }

    public function respond(User $actor, CalendarEvent $proposal, string $action, string $version): void
    {
        DB::transaction(function () use ($actor, $proposal, $action, $version) {
            User::lockForUpdate()->findOrFail($proposal->user_id);
            Workspace::lockForUpdate()->findOrFail($proposal->workspace_id);
            $proposal = CalendarEvent::proposals()->lockForUpdate()->findOrFail($proposal->id);
            abort_unless(in_array($actor->id, [$proposal->proposed_by, $proposal->user_id], true) && $this->calendars->scope($actor, $proposal->workspace, $proposal->project), 403);
            if ($proposal->task_id) {
                abort_unless($proposal->task && Gate::forUser($actor)->allows('view', $proposal->task), 403);
            }
            if (! hash_equals($this->calendar->version($proposal), $version)) {
                throw ValidationException::withMessages(['version' => 'Đề xuất đã thay đổi. Tải lại trước khi xử lý.']);
            }
            if ($proposal->status === CalendarEventStatus::PLANNED && $action === 'cancel' && $actor->id === $proposal->user_id) {
                // Accepted: the row is the recipient's own event, cancelled through the normal calendar rules.
                $this->calendar->save($actor, $proposal->workspace, [
                    'project_id' => $proposal->project_id,
                    'task_id' => $proposal->task_id,
                    'event_type' => $proposal->event_type->value,
                    'title' => $proposal->title,
                    'description' => $proposal->description,
                    'starts_at' => $proposal->starts_at->setTimezone($proposal->timezone)->format('Y-m-d\TH:i'),
                    'ends_at' => $proposal->ends_at->setTimezone($proposal->timezone)->format('Y-m-d\TH:i'),
                    'timezone' => $proposal->timezone,
                    'visibility' => $proposal->visibility->value,
                    'status' => CalendarEventStatus::CANCELLED->value,
                    'all_day' => false,
                    'is_busy' => true,
                    'participants' => $proposal->participants,
                    'version' => $this->calendar->version($proposal),
                ], $proposal);
                $proposal = CalendarEvent::proposals()->findOrFail($proposal->id);
                $proposal->fill(['responded_at' => now()])->save();
            } else {
                if ($proposal->status !== CalendarEventStatus::PROPOSED) {
                    throw ValidationException::withMessages(['proposal' => 'Đề xuất đã được xử lý. Không tạo lịch lần nữa.']);
                }
                abort_unless($action === 'cancel' ? $actor->id === $proposal->proposed_by : $actor->id === $proposal->user_id, 403);
                if ($action === 'accept') {
                    abort_unless($proposal->proposer && $this->calendars->scope($proposal->proposer, $proposal->workspace, $proposal->project, 'calendar.create'), 403);
                    if ($proposal->starts_at->isPast()) {
                        throw ValidationException::withMessages(['proposal' => 'Giờ đề xuất đã qua. Hãy tạo đề xuất mới.']);
                    }
                    if ($proposal->task_id) {
                        abort_unless($this->tasks->scope($proposal->proposer, $proposal->workspace, $proposal->project, 'task.assign'), 403);
                    }
                    $proposal = $this->calendar->acceptProposal($actor, $proposal->workspace, $proposal);
                } else {
                    $proposal->fill([
                        'status' => $action === 'decline' ? CalendarEventStatus::DECLINED : CalendarEventStatus::WITHDRAWN,
                        'responded_at' => now(),
                    ])->save();
                }
            }
            $other = User::find($actor->id === $proposal->user_id ? $proposal->proposed_by : $proposal->user_id);
            if ($other && $this->calendars->scope($other, $proposal->workspace, $proposal->project) && (! $proposal->task_id || $proposal->task && Gate::forUser($other)->allows('view', $proposal->task))) {
                $this->notifications->direct($other, $proposal->workspace, $proposal->project, 'calendar.responded', 'Đề xuất lịch đã được xử lý', $proposal->title.' · '.$proposal->status->value, $this->notificationKey($proposal), $proposal->task);
            }
        }, 3);
    }

    /** One notification per proposal transition; the id is read back by the notification link (focus). */
    private function notificationKey(CalendarEvent $proposal): string
    {
        return 'calendar-proposal:'.$proposal->id.':'.$proposal->status->value;
    }
}
