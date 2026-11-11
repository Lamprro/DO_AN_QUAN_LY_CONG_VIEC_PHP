<?php

namespace App\Services\Calendar;

use App\Authorization\CalendarAccess;
use App\Authorization\ProjectAccess;
use App\Authorization\TaskAccess;
use App\Authorization\WorkspaceAccess;
use App\Enums\CalendarEventStatus;
use App\Models\CalendarEvent;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Task\TaskHistory;
use App\Support\LocalTime;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CalendarService
{
    public function __construct(private CalendarAccess $calendars, private TaskAccess $tasks, private TaskHistory $history, private ProjectAccess $projects, private WorkspaceAccess $workspaces) {}

    public function version(CalendarEvent $event): string
    {
        return hash('sha256', json_encode($event->getRawOriginal()));
    }

    private function fail(string $key, string $text): never
    {
        throw ValidationException::withMessages([$key => $text]);
    }

    public function save(User $actor, Workspace $workspace, array $data, ?CalendarEvent $event = null): CalendarEvent
    {
        return DB::transaction(function () use ($actor, $workspace, $data, $event) {
            User::lockForUpdate()->findOrFail($actor->id);
            // Serialize all bookings for this owner, across workspaces.
            $workspace = Workspace::lockForUpdate()->findOrFail($workspace->id);
            if ($event) {
                $event = CalendarEvent::where('workspace_id', $workspace->id)->lockForUpdate()->findOrFail($event->id);
                Gate::forUser($actor)->authorize('update', $event);
                if (! hash_equals($this->version($event), $data['version'])) {
                    $this->fail('version', 'Lịch đã thay đổi; tải lại trước khi lưu.');
                }
                foreach (['project_id', 'task_id', 'event_type'] as $key) {
                    $value = $event->getAttribute($key);
                    $value = $value instanceof \BackedEnum ? $value->value : $value;
                    if ($value !== ($data[$key] ?? null)) {
                        $this->fail($key, 'Không chuyển phạm vi, task hoặc loại của sự kiện đã tạo.');
                    }
                }
                if ($event->timeEntries()->exists()) {
                    $this->fail('event', 'Lịch đã có giờ thực tế; giữ lịch sử, không sửa.');
                }
            }
            $project = empty($data['project_id']) ? null : $workspace->projects()->findOrFail($data['project_id']);
            abort_unless($this->tasks->projectWritable($project) && $this->calendars->scope($actor, $workspace, $project, $event ? 'calendar.update' : 'calendar.create'), 403);
            if ($data['visibility'] === 'project' && ! $project) {
                $this->fail('visibility', 'Chia sẻ project cần chọn project.');
            }
            // Project-scoped content may never be published to the entire workspace.
            if ($project && $data['visibility'] === 'workspace') {
                $this->fail('visibility', 'Lịch thuộc project chỉ chia sẻ trong project hoặc để riêng tư.');
            }
            $task = $this->taskFor($actor, $workspace, $project, $data, (bool) $event);
            $participants = array_values($data['participants'] ?? []);
            $eligible = $project ? $this->projects->eligibleMembers($workspace, $project)->pluck('user_id')->all() : $workspace
                ->members()
                ->where('status', 'active')
                ->with('user')
                ->get()
                ->filter(fn ($member) => $this->workspaces->allows($member->user, $workspace, 'workspace.view') && $this->workspaces->allows($member->user, $workspace, 'calendar.view'))
                ->pluck('user_id')
                ->all();
            $keepsHistoricalParticipants = $event && $data['status'] === 'cancelled' && $participants === array_values($event->participants ?? []);
            if (array_diff($participants, $eligible) && ! $keepsHistoricalParticipants) {
                $this->fail('participants', 'Người tham gia phải đang hoạt động, có quyền xem task trong phạm vi.');
            }
            $start = LocalTime::parse($data['starts_at'], $data['timezone'], (bool) $data['all_day']);
            $end = LocalTime::parse($data['ends_at'], $data['timezone'], (bool) $data['all_day']);
            if ($end->lte($start) || $end->gt($start->addDays(93))) {
                $this->fail('ends_at', 'Kết thúc phải sau bắt đầu, tối đa 93 ngày. Ngày kết thúc all-day không bao gồm.');
            }
            $busy = in_array($data['event_type'], ['TASK_BLOCK', 'BUSY', 'MEETING'], true) || $data['event_type'] === 'NOTE' && (bool) $data['is_busy'];
            if ($busy && $data['status'] !== 'cancelled') {
                $this->assertFree($actor, $start, $end, $event?->id);
            }
            $values = [
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'event_type' => $data['event_type'],
                'project_id' => $project?->id,
                'task_id' => $task?->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'timezone' => $data['timezone'],
                'all_day' => (bool) $data['all_day'],
                'is_busy' => $busy,
                'visibility' => $data['visibility'],
                'status' => $data['status'],
                'participants' => $participants,
            ];
            $changed = ! $event || $event->fill($values)->isDirty();
            $event = $event ? tap($event)->save() : CalendarEvent::create($values + [
                'workspace_id' => $workspace->id,
                'user_id' => $actor->id,
                'source' => 'manual',
            ]);
            if ($changed && $task) {
                $this->history->record($actor, $task, 'calendar.saved', [
                    'event_id' => $event->id,
                    'starts_at' => $start->toIso8601String(),
                    'ends_at' => $end->toIso8601String(),
                    'status' => $event->status->value,
                ]);
            }

            return $event;
        }, 3);
    }

    /**
     * Team Calendar: the recipient accepts a proposal. The same row becomes their planned event
     * after the checks a normal booking gets (scope, task assignment, busy overlap).
     */
    public function acceptProposal(User $actor, Workspace $workspace, CalendarEvent $proposal): CalendarEvent
    {
        return DB::transaction(function () use ($actor, $workspace, $proposal) {
            User::lockForUpdate()->findOrFail($actor->id);
            $workspace = Workspace::lockForUpdate()->findOrFail($workspace->id);
            $event = CalendarEvent::proposals()->where('workspace_id', $workspace->id)->lockForUpdate()->findOrFail($proposal->id);
            abort_unless($event->user_id === $actor->id && $event->status === CalendarEventStatus::PROPOSED, 403);
            $project = $event->project;
            abort_unless($this->tasks->projectWritable($project) && $this->calendars->scope($actor, $workspace, $project, 'calendar.create'), 403);
            $task = $this->taskFor($actor, $workspace, $project, [
                'event_type' => $event->event_type->value,
                'task_id' => $event->task_id,
                'all_day' => $event->all_day,
                'status' => CalendarEventStatus::PLANNED->value,
            ], false);
            $this->assertFree($actor, $event->starts_at, $event->ends_at, $event->id);
            $event->fill(['status' => CalendarEventStatus::PLANNED, 'responded_at' => now()])->save();
            if ($task) {
                $this->history->record($actor, $task, 'calendar.saved', [
                    'event_id' => $event->id,
                    'starts_at' => $event->starts_at->toIso8601String(),
                    'ends_at' => $event->ends_at->toIso8601String(),
                    'status' => $event->status->value,
                ]);
            }

            return $event;
        }, 3);
    }

    /** TASK_BLOCK must point to an open WORK assigned to the owner; other types carry no task. */
    private function taskFor(User $actor, Workspace $workspace, ?Project $project, array $data, bool $existing): ?Task
    {
        if ($data['event_type'] !== 'TASK_BLOCK') {
            if (! empty($data['task_id'])) {
                $this->fail('task_id', 'Chỉ TASK_BLOCK nhận task.');
            }

            return null;
        }
        $task = $workspace->tasks()->where('project_id', $project?->id)->find($data['task_id'] ?? null);
        if (! $task || $task->task_type->value !== 'WORK' || ! Gate::forUser($actor)->allows('view', $task) || ! ($existing && $data['status'] === 'cancelled') && (in_array($task->status->value, ['DONE', 'CANCELLED'], true) || $task->assignee_id !== $actor->id || ! $this->tasks->mutable($actor, $task, 'task.update'))) {
            $this->fail('task_id', 'Chọn WORK đang mở được giao cho bạn và có quyền cập nhật trong đúng phạm vi.');
        }
        if ($data['all_day']) {
            $this->fail('all_day', 'Khung làm task cần giờ bắt đầu/kết thúc cụ thể.');
        }

        return $task;
    }

    /** No overlap with the owner's busy, non-cancelled events (pending proposals are excluded by the model scope). */
    private function assertFree(User $owner, CarbonInterface $start, CarbonInterface $end, ?string $ignoreId): void
    {
        if (CalendarEvent::where('user_id', $owner->id)
            ->where('is_busy', true)
            ->where('status', '!=', CalendarEventStatus::CANCELLED->value)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->exists()) {
            $this->fail('starts_at', 'Khoảng giờ này trùng lịch bận của bạn.');
        }
    }

    public function delete(User $actor, Workspace $workspace, CalendarEvent $event, string $version): void
    {
        DB::transaction(function () use ($actor, $workspace, $event, $version) {
            User::lockForUpdate()->findOrFail($actor->id);
            Workspace::lockForUpdate()->findOrFail($workspace->id);
            $event = CalendarEvent::where('workspace_id', $workspace->id)->lockForUpdate()->findOrFail($event->id);
            Gate::forUser($actor)->authorize('delete', $event);
            if (! hash_equals($this->version($event), $version)) {
                $this->fail('version', 'Lịch đã thay đổi; tải lại trước khi xóa.');
            }
            if ($event->timeEntries()->exists()) {
                $this->fail('event', 'Sự kiện có giờ thực tế; không xóa.');
            }
            if ($event->task) {
                $this->history->record($actor, $event->task, 'calendar.deleted', ['event_id' => $event->id]);
            }
            $event->delete();
        }, 3);
    }
}
