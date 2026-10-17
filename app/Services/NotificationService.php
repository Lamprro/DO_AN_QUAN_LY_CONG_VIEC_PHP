<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\TaskPolicy;
use App\Support\ProjectAccess;
use App\Support\WorkspaceAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function __construct(private SettingService $settings, private TaskPolicy $tasks, private WorkspaceAccess $workspaces, private ProjectAccess $projects) {}

    public function taskEvent(TaskActivity $history, Task $task, User $actor, array $data): void
    {
        $event = $history->event_type;
        $ids = [];
        $title = '';
        if ($event === 'task.created' || ($event === 'task.updated' && array_key_exists('assignee_id', $data['after'] ?? []) && ($data['before']['assignee_id'] ?? null) !== $task->assignee_id)) {
            $ids = [$task->assignee_id];
            $title = 'Bạn được giao công việc';
        } elseif ($event === 'task.status_changed') {
            $ids = ($data['to'] ?? null) === 'REVIEW' ? [$task->reviewer_id] : [$task->assignee_id, $task->created_by];
            $title = ($data['to'] ?? null) === 'REVIEW' ? 'Công việc đang chờ bạn duyệt' : 'Trạng thái công việc đã thay đổi';
        } elseif ($event === 'task.comment_added') {
            $ids = [$task->assignee_id, $task->reviewer_id, $task->created_by];
            $title = 'Có bình luận mới';
        }
        foreach (array_unique(array_filter($ids)) as $id) {
            if ($id === $actor->id) {
                continue;
            }
            $recipient = User::find($id);
            if (! $recipient || $recipient->status->value !== 'active' || ! $this->tasks->view($recipient, $task) || ! $this->settings->values($recipient)['notifications']['in_app']) {
                continue;
            }
            Notification::firstOrCreate(['dedupe_key' => 'activity:'.$history->id.':'.$id], ['workspace_id' => $task->workspace_id, 'project_id' => $task->project_id, 'task_id' => $task->id,
                'recipient_id' => $id, 'type' => $event, 'title' => $title, 'body' => $task->code.' · '.$task->title,
                'deliveries' => [['channel' => 'in_app', 'status' => 'sent', 'attempts' => 1, 'sent_at' => now()->toIso8601String(), 'last_error' => null]]]);
        }
    }

    public function visible(User $user): Builder
    {
        $spaces = Workspace::whereHas('members', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))->get()
            ->filter(fn ($w) => $this->workspaces->allows($user, $w, 'workspace.view'));
        $projects = $spaces->flatMap(fn ($w) => $this->projects->visible($user, $w)->get());
        $taskProjects = $projects->filter(fn ($p) => $this->projects->allows($user, $p, 'task.view'))->pluck('id');
        $personal = $spaces->filter(fn ($w) => $w->kind->value === 'personal' && $w->owner_id === $user->id && $this->workspaces->allows($user, $w, 'task.view'))->pluck('id');

        return Notification::where('recipient_id', $user->id)
            ->where(fn ($q) => $q->whereNull('workspace_id')->orWhereIn('workspace_id', $spaces->pluck('id')))
            ->where(fn ($q) => $q->whereNull('project_id')->orWhereIn('project_id', $projects->pluck('id')))
            ->where(fn ($q) => $q->whereNull('task_id')->orWhereHas('task', fn ($t) => $t->whereIn('project_id', $taskProjects)->orWhere(fn ($t) => $t->whereNull('project_id')->whereIn('workspace_id', $personal))));
    }

    public function read(User $user, ?string $id = null, bool $unread = false): void
    {
        DB::transaction(function () use ($user, $id, $unread) {
            $query = $this->visible($user);
            if ($id) {
                $notification = $query->lockForUpdate()->findOrFail($id);
                $notification->update(['read_at' => $unread ? null : ($notification->read_at ?? now())]);
            } else {
                $query->whereNull('read_at')->update(['read_at' => now()]);
            }
        }, 3);
    }
}
