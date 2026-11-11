<?php

namespace App\Services\Planning;

use App\Authorization\PlanAccess;
use App\Authorization\ProjectAccess;
use App\Authorization\TaskAccess;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Support\SearchTerm;
use Illuminate\Validation\ValidationException;

/** Read model for plan screens. */
class PlanPageData
{
    public function __construct(private PlanAccess $plans, private TaskAccess $tasks, private ProjectAccess $projects, private PlanService $service, private PlanReviewService $review) {}

    public function assertScope(User $actor, Workspace $workspace, ?Project $project, string $permission = 'plan.view'): void
    {
        abort_unless($this->plans->allows($actor, $workspace, $project, $permission), 403);
    }

    /** The plan belongs to the workspace, uses the current payload schema and is visible. */
    public function assertPlan(User $actor, Workspace $workspace, Plan $plan): void
    {
        abort_unless($plan->workspace_id === $workspace->id, 404);
        abort_unless(($plan->data['schema_version'] ?? null) === 1 && $this->plans->allows($actor, $workspace, $plan->project), 403);
    }

    public function index(User $actor, Workspace $workspace, ?Project $project): array
    {
        $this->assertScope($actor, $workspace, $project);

        return [
            'workspace' => $workspace,
            'project' => $project,
            'plans' => Plan::where('workspace_id', $workspace->id)
                ->where('project_id', $project?->id)
                ->where('data->schema_version', 1)
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'canCreatePlan' => $this->plans->allows($actor, $workspace, $project, 'plan.create'),
        ];
    }

    public function show(User $actor, Workspace $workspace, Plan $plan): array
    {
        $project = $plan->project;
        $writable = $this->tasks->projectWritable($project);
        $canPreview = $this->plans->allows($actor, $workspace, $project, 'plan.apply') && $this->plans->allows($actor, $workspace, $project, 'plan.approve');
        $canRevise = $this->plans->allows($actor, $workspace, $project, 'plan.create') && $this->plans->allows($actor, $workspace, $project, 'plan.update') && $writable;
        $actions = [];
        foreach ([
            'propose' => ['draft', 'plan.update', 'Đề xuất duyệt'],
            'approve' => ['proposed', 'plan.approve', 'Duyệt kế hoạch'],
            'reject' => ['proposed', 'plan.approve', 'Từ chối'],
            'apply' => ['approved', 'plan.apply', 'Áp dụng'],
        ] as $action => [$status, $permission, $label]) {
            if ($plan->status->value === $status && ! $plan->applied_at && $writable && $this->plans->allows($actor, $workspace, $project, $permission) && ($action !== 'apply' || $canPreview)) {
                $actions[$action] = $label;
            }
        }

        return [
            'workspace' => $workspace,
            'project' => $project,
            'plan' => $plan,
            'parent' => $plan->parent,
            'impact' => $this->service->impact($actor, $workspace, $project),
            'diff' => $this->review->diff($plan),
            'canPreview' => $canPreview,
            'canRevise' => $canRevise,
            'canSchedule' => $canRevise,
            'actions' => $actions,
            'users' => $this->projects->eligibleMembers($workspace, $project)->mapWithKeys(fn ($m) => [$m->user_id => $m->user->name])->all(),
        ];
    }

    /** Base versions offered to planning AI. */
    public function bases(User $actor, Workspace $workspace, ?Project $project)
    {
        $this->assertScope($actor, $workspace, $project, 'plan.create');

        return Plan::where('workspace_id', $workspace->id)->where('project_id', $project?->id)->where('data->schema_version', 1)->latest()->limit(100)->get();
    }

    public function build(User $actor, Workspace $workspace, ?Project $project, array $payload, ?string $sourceQuery = null, array $sourceSelected = []): array
    {
        $rawDraft = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $renderable = true;
        foreach ([
            'tasks' => 100,
            'dependencies' => 300,
            'slots' => 300,
        ] as $list => $limit) {
            if (! is_array($payload[$list] ?? null) || ! array_is_list($payload[$list]) || count($payload[$list]) > $limit) {
                $renderable = false;
                break;
            }
            foreach ($payload[$list] as $row) {
                if (! is_array($row) || array_filter($row, fn ($v) => ! is_scalar($v) && $v !== null)) {
                    $renderable = false;
                    break 2;
                }
            }
        }
        if ($renderable) {
            foreach ($payload['tasks'] as $row) {
                if (! is_string($row['ref'] ?? null) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,39}$/D', $row['ref']) || ! in_array($row['task_type'] ?? null, ['GROUP', 'WORK', 'MILESTONE'], true) || ! in_array($row['priority'] ?? null, ['low', 'medium', 'high', 'urgent'], true)) {
                    $renderable = false;
                    break;
                }
            }
        }
        if (! $renderable) {
            $payload = [
                'schema_version' => 1,
                'tasks' => [],
                'dependencies' => [],
                'slots' => [],
            ];
        }
        $members = $this->projects->eligibleMembers($workspace, $project);
        $reviewers = $this->projects->eligibleMembers($workspace, $project, 'task.review');
        $query = $workspace->tasks()->where('project_id', $project?->id)->whereNull('archived_at');
        if (! $this->tasks->scope($actor, $workspace, $project, 'task.view')) {
            $query->whereRaw('1 = 0');
        }
        $needle = $sourceQuery;
        $choices = (clone $query)
            ->when(is_string($needle) && $needle !== '', fn ($q) => $q->where(fn ($q) => $q->whereRaw("code LIKE ? ESCAPE '!'", [SearchTerm::contains($needle)])->orWhereRaw("title LIKE ? ESCAPE '!'", [SearchTerm::contains($needle)])))
            ->orderBy('code')
            ->limit(201)
            ->get();
        $hasMore = $choices->count() > 200;
        $choices = $choices->take(200);
        $selected = [];
        foreach ($payload['tasks'] as $row) {
            if (! empty($row['task_id'])) {
                $selected[] = $row['task_id'];
            }
        }
        $selected = array_unique(array_merge($selected, $sourceSelected));
        if ($selected) {
            $choices = $choices->merge((clone $query)->whereIn('id', $selected)->limit(100)->get())->unique('id')->values();
        }
        // Include at most three ancestor batches so a searched child keeps its actual group.
        $parents = $choices->pluck('parent_task_id')->filter()->all();
        for ($depth = 0; $depth < 3 && $parents; $depth++) {
            $ancestors = (clone $query)->whereIn('id', $parents)->get();
            $choices = $choices->merge($ancestors)->unique('id')->values();
            $parents = $ancestors->pluck('parent_task_id')->filter()->all();
        }
        $tasks = $choices->map(fn ($t) => [
            'id' => $t->id,
            'label' => $t->code.' — '.$t->title,
            'title' => $t->title,
            'description' => $t->description,
            'task_type' => $t->task_type->value,
            'priority' => $t->priority->value,
            'estimate_minutes' => $t->estimate_minutes,
            'assignee_id' => $t->assignee_id,
            'reviewer_id' => $t->reviewer_id,
            'parent_task_id' => $t->parent_task_id,
            'editable' => $actor->can('update', $t) && $t->status->value === 'TODO' && ! $t->started_at,
        ])->all();
        $users = $members->map(fn ($m) => ['id' => $m->user_id, 'label' => $m->user->name])->all();
        $review = $reviewers->map(fn ($m) => ['id' => $m->user_id, 'label' => $m->user->name])->all();

        return [
            'payload' => $payload,
            'renderable' => $renderable,
            'source_limit_reached' => $hasMore,
            'rawDraft' => $rawDraft,
            'tasks' => $tasks,
            'users' => $users,
            'reviewers' => $review,
            'timezone' => $workspace->timezone,
            'limits' => [
                'tasks' => 100,
                'dependencies' => 300,
                'slots' => 300,
                'depth' => 3,
            ],
        ];
    }

    public function normalize(array $editor): array
    {
        $result = ['schema_version' => 1];
        foreach (['tasks', 'dependencies', 'slots'] as $list) {
            if (! isset($editor[$list])) {
                $result[$list] = [];

                continue;
            }
            if (! is_array($editor[$list])) {
                $result[$list] = $editor[$list];

                continue;
            }
            $result[$list] = array_values(array_map(function ($row) {
                if (! is_array($row)) {
                    return $row;
                }
                foreach ($row as $field => $value) {
                    if ($value === '') {
                        $row[$field] = null;
                    }
                }

                return $row;
            }, $editor[$list]));
        }

        return $result;
    }

    /** Prepare the draft editor without reading HTTP or session state. */
    public function form(User $actor, Workspace $workspace, ?Project $project, ?string $baseId, array $oldInput, ?string $sourceQuery, array $sourceSelected): array
    {
        $this->assertScope($actor, $workspace, $project, 'plan.create');
        $base = null;
        $payload = [
            'schema_version' => 1,
            'tasks' => [
                [
                    'ref' => 'work1',
                    'title' => 'Công việc mới',
                    'task_type' => 'WORK',
                    'priority' => 'medium',
                    'estimate_minutes' => 60,
                    'assignee_id' => $actor->id,
                ],
            ],
            'dependencies' => [],
            'slots' => [],
        ];
        if (filled($baseId)) {
            $base = Plan::findOrFail($baseId);
            $this->assertPlan($actor, $workspace, $base);
            abort_unless($base->project_id === $project?->id, 404);
            try {
                $payload = $this->service->payload($base);
            } catch (ValidationException $e) {
                return ['invalidBase' => $base];
            }
            foreach ($payload['tasks'] as &$row) {
                if (isset($base->data['applied_task_ids'][$row['ref']])) {
                    $row['task_id'] = $base->data['applied_task_ids'][$row['ref']];
                }
            }
            unset($row);
        }
        // Re-display the user's draft after a validation error.
        if (($oldInput['input_mode'] ?? null) === 'editor') {
            $old = $oldInput['payload'] ?? null;
            if (is_string($old) && mb_strlen($old) <= 120000) {
                $decoded = json_decode($old, true, 32);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            } elseif (is_array($oldInput['editor'] ?? null)) {
                $payload = $this->normalize($oldInput['editor'] ?? null);
            }
        }
        $editorData = $this->build($actor, $workspace, $project, $payload, $sourceQuery, $sourceSelected);
        if (is_string($oldInput['payload'] ?? null) && mb_strlen($oldInput['payload'] ?? null) > 120000) {
            $editorData['renderable'] = false;
        }
        $people = $this->projects->eligibleMembers($workspace, $project);

        return compact('workspace', 'project', 'base', 'payload', 'people', 'editorData');
    }
}
