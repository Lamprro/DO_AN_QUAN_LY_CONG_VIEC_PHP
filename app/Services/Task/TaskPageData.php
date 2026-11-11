<?php

namespace App\Services\Task;

use App\Authorization\CalendarAccess;
use App\Authorization\PlanAccess;
use App\Authorization\ProjectAccess;
use App\Authorization\TaskAccess;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\SavedTaskFilter;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Support\SearchTerm;
use App\Support\UiLabels;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read model for task screens (list, board, tree, form, detail).
 * Scope pages assert task access here; single-task pages are authorized by the controller.
 */
class TaskPageData
{
    public function __construct(private TaskAccess $tasks, private TaskListingQuery $listing, private ProjectAccess $projects, private PlanAccess $plans, private CalendarAccess $calendars, private TaskService $service, private TaskTreeService $tree, private TaskWorkflowService $workflow) {}

    public function assertScope(User $actor, Workspace $workspace, ?Project $project, string $permission = 'task.view'): void
    {
        abort_unless($this->tasks->scope($actor, $workspace, $project, $permission), 403);
    }

    /** Data shared by every task screen of a scope (form options, route names). */
    public function context(User $actor, Workspace $workspace, ?Project $project): array
    {
        return [
            'deadlinePresets' => $this->service->presets($workspace),
            'workspace' => $workspace,
            'project' => $project,
            'routePrefix' => $project ? 'workspaces.projects.tasks' : 'workspaces.tasks',
            'routeParams' => $project ? [$workspace, $project] : [$workspace],
            'canCreate' => $this->canCreate($actor, $workspace, $project),
            'parents' => $workspace->tasks()->where('project_id', $project?->id)->where('task_type', 'GROUP')->whereNull('archived_at')->orderBy('sort_order')->get(),
            'people' => $this->projects->eligibleMembers($workspace, $project),
            'reviewers' => $this->projects->eligibleMembers($workspace, $project, 'task.review'),
            'canAssign' => $this->tasks->scope($actor, $workspace, $project, 'task.assign'),
        ];
    }

    public function canCreate(User $actor, Workspace $workspace, ?Project $project): bool
    {
        return $this->tasks->projectWritable($project) && $this->tasks->scope($actor, $workspace, $project, 'task.create');
    }

    public function list(User $actor, Workspace $workspace, ?Project $project, array $filters): array
    {
        $tasks = $this->filtered($actor, $workspace, $project, $filters)->orderBy('id')->paginate((int) ($filters['per_page'] ?? 10))->withQueryString();
        $savedFilters = SavedTaskFilter::where('user_id', $actor->id)->where('workspace_id', $workspace->id)->where('project_id', $project?->id)->orderBy('name')->get();

        return $this->context($actor, $workspace, $project) + compact('tasks', 'filters', 'savedFilters') + ['isPersonalOverview' => $this->listing->isPersonalOverview($actor, $workspace, $project)];
    }

    public function board(User $actor, Workspace $workspace, ?Project $project, array $filters): array
    {
        $query = $this->filtered($actor, $workspace, $project, $filters)->withCount(['children', 'children as completed_children_count' => fn ($children) => $children->where('status', 'DONE')]);
        $board = [];
        foreach (TaskStatus::cases() as $status) {
            $board[$status->value] = (clone $query)
                ->where('task_type', '!=', 'GROUP')
                ->where('status', $status->value)
                ->orderBy('id')
                ->paginate(10, ['*'], 'page_'.strtolower($status->value))
                ->withQueryString();
        }

        return $this->context($actor, $workspace, $project) + compact('board', 'filters') + ['isPersonalOverview' => $this->listing->isPersonalOverview($actor, $workspace, $project)];
    }

    public function createForm(User $actor, Workspace $workspace, ?Project $project): array
    {
        $this->assertScope($actor, $workspace, $project);
        $context = $this->context($actor, $workspace, $project);
        abort_unless($context['canCreate'], 403);

        return $context;
    }

    public function tree(User $actor, Workspace $workspace, ?Project $project): array
    {
        $tasks = $this->listing->query($actor, $workspace, $project)->orderBy('sort_order')->orderBy('id')->get();
        $isPersonalOverview = $this->listing->isPersonalOverview($actor, $workspace, $project);
        // Project groups are not assigned. Promote matching leaves without loading other people's tasks.
        $visibleIds = $tasks->modelKeys();
        $nodes = $tasks->groupBy(fn ($task) => $task->parent_task_id && in_array($task->parent_task_id, $visibleIds, true) ? $task->parent_task_id : 'root');

        return [
            'workspace' => $workspace,
            'project' => $project,
            'tasks' => $tasks,
            'nodes' => $nodes,
            'isPersonalOverview' => $isPersonalOverview,
            'progress' => $this->tree->progress($tasks),
            'routePrefix' => $project ? 'workspaces.projects.tasks' : 'workspaces.tasks',
            'routeParams' => $project ? [$workspace, $project] : [$workspace],
        ];
    }

    public function panel(User $actor, Workspace $workspace, ?Project $project, Task $task): array
    {
        return $this->context($actor, $workspace, $project) + [
            'task' => $task,
            'panelMode' => 'view',
            'workflowUi' => $this->workflow->availability($actor, $task),
            'recentComments' => $task->activities()->where('type', 'COMMENT')->with('actor')->latest()->limit(5)->get(),
        ];
    }

    public function show(User $actor, Workspace $workspace, ?Project $project, Task $task, ?string $activityType): array
    {
        $all = $workspace->tasks()->where('project_id', $project?->id)->get();
        $activities = $task
            ->activities()
            ->with('actor')
            ->when($activityType, fn ($q) => $q->where('type', $activityType))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();
        $historyPeople = User::whereIn('id', $workspace->members()->pluck('user_id'))->pluck('name', 'id')->all();
        $historyTasks = $all->pluck('title', 'id')->all();
        $activityHuman = function ($field, $value) use ($historyPeople, $historyTasks) {
            if (in_array($field, ['assignee_id', 'reviewer_id', 'creator_id'], true)) {
                return $value ? $historyPeople[$value] ?? 'Không còn trong workspace' : 'Chưa phân công';
            }
            if (in_array($field, ['parent_task_id', 'predecessor_id', 'predecessor_task_id'], true)) {
                return $value ? $historyTasks[$value] ?? 'Không còn trong phạm vi' : 'Không có';
            }
            if ($value === null) {
                return 'Chưa có';
            }

            return is_scalar($value) ? in_array($field, ['status', 'priority', 'task_type'], true) ? UiLabels::text($value) : (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        };

        return $this->context($actor, $workspace, $project) + [
            'task' => $task,
            'activities' => $activities,
            'activityHuman' => $activityHuman,
            'dependencies' => $task->incomingDependencies()->with('predecessor')->get(),
            'blockers' => $task->blockers()->with('reporter', 'resolver')->orderByDesc('created_at')->get(),
            'candidates' => $all->filter(fn ($t) => $t->id !== $task->id && $t->task_type->value !== 'GROUP' && ! $t->archived_at),
            'progress' => $this->tree->progress($this->tree->descendants($task, $all)),
            'subtasks' => $all->where('parent_task_id', $task->id)->sortBy('sort_order'),
            'deletable' => $task->status->value === 'TODO' && ! $task->started_at && ! $task->completed_at && ! $task->activities()->withTrashed()->exists(),
            'deadlineInsight' => $this->service->deadlineInsight($task),
            'workflowUi' => $this->workflow->availability($actor, $task),
            'statusOptions' => $this->workflow->options($actor, $task),
            'canViewPlans' => $this->plans->allows($actor, $workspace, $project, 'plan.view'),
            'canScheduleTask' => $task->assignee_id === $actor->id && $task->task_type->value === 'WORK' && in_array($task->status->value, ['TODO', 'IN_PROGRESS', 'REVIEW'], true) && $this->tasks->mutable($actor, $task, 'task.update') && $this->calendars->scope($actor, $workspace, $project, 'calendar.create'),
        ] + $this->workflow->collaborationData($task);
    }

    public function statusPanel(User $actor, Workspace $workspace, ?Project $project, Task $task): array
    {
        return compact('workspace', 'project', 'task') + [
            'panelMode' => 'status',
            'workflowUi' => $this->workflow->availability($actor, $task),
            'routePrefix' => $project ? 'workspaces.projects.tasks' : 'workspaces.tasks',
            'routeParams' => $project ? [$workspace, $project] : [$workspace],
        ];
    }

    /** List/board query: archive state, search, field filters, preset and sort. */
    private function filtered(User $actor, Workspace $workspace, ?Project $project, array $filters): Builder
    {
        $query = $this->listing->query($actor, $workspace, $project);
        $archive = $filters['archived'] ?? 'active';
        if ($archive !== 'all') {
            $archive === 'archived' ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');
        }
        $query->when(isset($filters['q']) && $filters['q'] !== '', fn ($q) => $q->where(fn ($s) => $s->whereRaw("title LIKE ? ESCAPE '!'", [SearchTerm::contains($filters['q'])])->orWhereRaw("code LIKE ? ESCAPE '!'", [SearchTerm::contains($filters['q'])])));
        foreach (['status', 'priority', 'assignee_id'] as $field) {
            $query->when($filters[$field] ?? null, fn ($q, $v) => $q->where($field, $v));
        }
        $this->applyPreset($query, $filters['preset'] ?? 'all', $actor, $workspace, $project);
        $direction = $filters['direction'] ?? 'desc';
        $sort = $filters['sort'] ?? 'created_at';
        if ($sort === 'priority') {
            $query->orderByRaw("CASE priority WHEN 'low' THEN 1 WHEN 'medium' THEN 2 WHEN 'high' THEN 3 WHEN 'urgent' THEN 4 ELSE 0 END ".$direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        return $query;
    }

    private function applyPreset(Builder $query, string $preset, User $actor, Workspace $workspace, ?Project $project): void
    {
        if ($preset === 'review' && $this->listing->isPersonalOverview($actor, $workspace, $project)) {
            $this->listing->awaitingReview($query, $actor, $workspace);

            return;
        }
        match ($preset) {
            'mine' => $query->where('assignee_id', $actor->id)->where('task_type', '!=', 'GROUP'),
            'review' => $query
                ->whereNull('archived_at')
                ->when(! $this->tasks->projectWritable($project), fn ($q) => $q->whereRaw('1=0'))
                ->where('status', 'REVIEW')
                ->where('task_type', '!=', 'GROUP')
                ->where(function ($q) use ($actor, $workspace) {
                    if ($workspace->owner_id !== $actor->id) {
                        $q->where('reviewer_id', $actor->id);
                    }
                })
                ->when(! $this->tasks->scope($actor, $workspace, $project, 'task.review'), fn ($q) => $q->whereRaw('1=0')),
            'blocked' => $query->where('task_type', '!=', 'GROUP')->whereNotIn('status', ['DONE', 'CANCELLED'])->whereHas('blockers', fn ($q) => $q->where('status', 'open')),
            'overdue' => $query->where('task_type', 'WORK')->whereNotIn('status', ['DONE', 'CANCELLED'])->where('deadline_at', '<', now()),
            default => null,
        };
    }
}
