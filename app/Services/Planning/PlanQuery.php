<?php

namespace App\Services\Planning;

use App\Authorization\PlanAccess;
use App\Authorization\ProjectAccess;
use App\Authorization\TaskAccess;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;

/** Read model for plan screens. */
class PlanQuery
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
            'workspace' => $workspace, 'project' => $project,
            'plans' => Plan::where('workspace_id', $workspace->id)->where('project_id', $project?->id)->where('data->schema_version', 1)->latest()->paginate(20)->withQueryString(),
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
        foreach (['propose' => ['draft', 'plan.update', 'Đề xuất duyệt'], 'approve' => ['proposed', 'plan.approve', 'Duyệt kế hoạch'], 'reject' => ['proposed', 'plan.approve', 'Từ chối'], 'apply' => ['approved', 'plan.apply', 'Áp dụng']] as $action => [$status, $permission, $label]) {
            if ($plan->status->value === $status && ! $plan->applied_at && $writable && $this->plans->allows($actor, $workspace, $project, $permission) && ($action !== 'apply' || $canPreview)) {
                $actions[$action] = $label;
            }
        }

        return [
            'workspace' => $workspace, 'project' => $project, 'plan' => $plan, 'parent' => $plan->parent,
            'impact' => $this->service->impact($actor, $workspace, $project),
            'diff' => $this->review->diff($plan),
            'canPreview' => $canPreview, 'canRevise' => $canRevise, 'canSchedule' => $canRevise, 'actions' => $actions,
            'users' => $this->projects->eligibleMembers($workspace, $project)->mapWithKeys(fn ($m) => [$m->user_id => $m->user->name])->all(),
        ];
    }

    /** Base versions offered to planning AI. */
    public function bases(User $actor, Workspace $workspace, ?Project $project)
    {
        $this->assertScope($actor, $workspace, $project, 'plan.create');

        return Plan::where('workspace_id', $workspace->id)->where('project_id', $project?->id)->where('data->schema_version', 1)->latest()->limit(100)->get();
    }
}
