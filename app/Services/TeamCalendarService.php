<?php

namespace App\Services;

use App\Models\CalendarProposal;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\CalendarEventPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeamCalendarService
{
    public function allowed(User $actor, Workspace $workspace, ?Project $project, string $permission = 'calendar.view'): bool
    {
        return app(CalendarEventPolicy::class)->scope($actor, $workspace, $project, $permission);
    }

    public function propose(User $actor, Workspace $workspace, array $data): CalendarProposal
    {
        $data = Validator::make($data, ['project_id' => 'nullable|uuid', 'recipient_id' => 'required|uuid', 'task_id' => 'nullable|uuid', 'title' => 'required|string|max:200', 'description' => 'nullable|string|max:4000', 'event_type' => ['required', Rule::in(['MEETING', 'TASK_BLOCK'])], 'visibility' => ['required', Rule::in(['private', 'project', 'workspace'])], 'timezone' => 'required|timezone', 'starts_at' => 'required|date_format:Y-m-d\TH:i', 'ends_at' => 'required|date_format:Y-m-d\TH:i'])->validate();

        return DB::transaction(function () use ($actor, $workspace, $data) {
            $workspace = Workspace::lockForUpdate()->findOrFail($workspace->id);
            $project = empty($data['project_id']) ? null : $workspace->projects()->findOrFail($data['project_id']);
            abort_unless($this->allowed($actor, $workspace, $project, 'calendar.create') && app(TaskPolicy::class)->projectWritable($project), 403);
            $recipient = User::findOrFail($data['recipient_id']);
            abort_unless($recipient->id !== $actor->id && $this->allowed($recipient, $workspace, $project, 'calendar.create'), 403);
            if ($data['visibility'] === 'project' && ! $project || $data['visibility'] === 'workspace' && $project) {
                throw ValidationException::withMessages(['visibility' => 'Chọn phạm vi chia sẻ phù hợp với project.']);
            }
            if ($data['event_type'] === 'TASK_BLOCK') {
                abort_unless(app(TaskPolicy::class)->scope($actor, $workspace, $project, 'task.assign'), 403);
                $task = $workspace->tasks()->where('project_id', $project?->id)->findOrFail($data['task_id'] ?? null);
                if (! app(TaskPolicy::class)->view($actor, $task) || ! app(TaskPolicy::class)->update($recipient, $task) || $task->assignee_id !== $recipient->id || $task->task_type->value !== 'WORK' || in_array($task->status->value, ['DONE', 'CANCELLED'])) {
                    throw ValidationException::withMessages(['task_id' => 'Chọn WORK đang mở giao cho người nhận và có quyền cập nhật.']);
                }
            } elseif (! empty($data['task_id'])) {
                throw ValidationException::withMessages(['task_id' => 'Cuộc họp không gắn task; khung làm việc dùng TASK_BLOCK.']);
            }
            $start = app(CalendarService::class)->localTime($data['starts_at'], $data['timezone'], false);
            $end = app(CalendarService::class)->localTime($data['ends_at'], $data['timezone'], false);
            if ($start->isPast() || $end->lte($start) || $end->gt($start->addDay())) {
                throw ValidationException::withMessages(['starts_at' => 'Chọn giờ tương lai, kết thúc sau bắt đầu và tối đa24 giờ.']);
            }
            $proposal = CalendarProposal::create(array_replace($data, ['workspace_id' => $workspace->id, 'project_id' => $project?->id, 'task_id' => $data['task_id'] ?? null, 'proposed_by' => $actor->id, 'starts_at' => $start, 'ends_at' => $end, 'status' => 'pending', 'revision' => 1]));
            app(NotificationService::class)->direct($recipient, $workspace, $project, 'calendar.proposed', 'Có đề xuất lịch cần bạn xác nhận', $proposal->title, 'calendar-proposal:'.$proposal->id.':pending', $proposal->task);

            return $proposal;
        }, 3);
    }

    public function respond(User $actor, CalendarProposal $proposal, string $action, int $revision): void
    {
        DB::transaction(function () use ($actor, $proposal, $action, $revision) {
            User::lockForUpdate()->findOrFail($proposal->recipient_id);
            Workspace::lockForUpdate()->findOrFail($proposal->workspace_id);
            $p = CalendarProposal::lockForUpdate()->findOrFail($proposal->id);
            abort_unless(in_array($actor->id, [$p->proposed_by, $p->recipient_id]) && $this->allowed($actor, $p->workspace, $p->project), 403);
            if ($p->task_id) {
                abort_unless($p->task && app(TaskPolicy::class)->view($actor, $p->task), 403);
            }
            if ($p->revision !== $revision) {
                throw ValidationException::withMessages(['revision' => 'Đề xuất đã thay đổi. Tải lại trước khi xử lý.']);
            }
            if ($p->status === 'accepted' && $action === 'cancel' && $actor->id === $p->recipient_id) {
                if ($p->event) {
                    $e = $p->event;
                    app(CalendarService::class)->save($actor, $p->workspace, ['project_id' => $e->project_id, 'task_id' => $e->task_id, 'event_type' => $e->event_type->value, 'title' => $e->title, 'description' => $e->description, 'starts_at' => $e->starts_at->setTimezone($e->timezone)->format('Y-m-d\TH:i'), 'ends_at' => $e->ends_at->setTimezone($e->timezone)->format('Y-m-d\TH:i'), 'timezone' => $e->timezone, 'visibility' => $e->visibility->value, 'status' => 'cancelled', 'all_day' => false, 'is_busy' => true, 'participants' => $e->participants, 'version' => app(CalendarService::class)->version($e)], $e);
                }
                $p->update(['status' => 'cancelled', 'revision' => $p->revision + 1, 'responded_at' => now()]);
            } else {
                if ($p->status !== 'pending') {
                    throw ValidationException::withMessages(['proposal' => 'Đề xuất đã được xử lý. Không tạo lịch lần nữa.']);
                }
                abort_unless($action === 'cancel' ? $actor->id === $p->proposed_by : $actor->id === $p->recipient_id, 403);
                if ($action === 'accept') {
                    abort_unless($p->proposer && $this->allowed($p->proposer, $p->workspace, $p->project, 'calendar.create'), 403);
                    if ($p->starts_at->isPast()) {
                        throw ValidationException::withMessages(['proposal' => 'Giờ đề xuất đã qua. Hãy tạo đề xuất mới.']);
                    }
                    if ($p->task_id) {
                        abort_unless(app(TaskPolicy::class)->scope($p->proposer, $p->workspace, $p->project, 'task.assign'), 403);
                    }
                    $event = app(CalendarService::class)->save($actor, $p->workspace, ['project_id' => $p->project_id, 'task_id' => $p->task_id, 'event_type' => $p->event_type, 'title' => $p->title, 'description' => $p->description, 'starts_at' => $p->starts_at->setTimezone($p->timezone)->format('Y-m-d\TH:i'), 'ends_at' => $p->ends_at->setTimezone($p->timezone)->format('Y-m-d\TH:i'), 'timezone' => $p->timezone, 'visibility' => $p->visibility, 'status' => 'planned', 'all_day' => false, 'is_busy' => true, 'participants' => []]);
                    $p->calendar_event_id = $event->id;
                }
                $p->fill(['status' => match ($action) {
                    'accept' => 'accepted','decline' => 'declined',default => 'cancelled'
                }, 'responded_at' => now(), 'revision' => $p->revision + 1])->save();
            }
            $other = User::find($actor->id === $p->recipient_id ? $p->proposed_by : $p->recipient_id);
            if ($other && $this->allowed($other, $p->workspace, $p->project) && (! $p->task_id || ($p->task && app(TaskPolicy::class)->view($other, $p->task)))) {
                app(NotificationService::class)->direct($other, $p->workspace, $p->project, 'calendar.responded', 'Đề xuất lịch đã được xử lý', $p->title.' · '.$p->status, 'calendar-proposal:'.$p->id.':'.$p->revision, $p->task);
            }
        }, 3);
    }
}
