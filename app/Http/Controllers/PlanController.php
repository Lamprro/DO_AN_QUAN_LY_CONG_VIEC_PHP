<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Project;
use App\Models\Workspace;
use App\Services\PlanScheduleService;
use App\Services\PlanService;
use App\Support\ProjectAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PlanController extends Controller
{
    public function __construct(private PlanService $service) {}

    private function scope(Request $request, Workspace $workspace, string $permission = 'plan.view'): ?Project
    {
        $request->validate(['project_id' => 'nullable|uuid']);
        $project = $request->filled('project_id') ? $workspace->projects()->findOrFail($request->input('project_id')) : null;
        abort_unless($this->service->allowed($request->user(), $workspace, $project, $permission), 403);

        return $project;
    }

    private function bound(Request $request, Workspace $workspace, Plan $plan): void
    {
        abort_unless($plan->workspace_id === $workspace->id, 404);
        abort_unless(($plan->data['schema_version'] ?? null) === 1 && $this->service->allowed($request->user(), $workspace, $plan->project), 403);
    }

    public function index(Request $request, Workspace $workspace)
    {
        $project = $this->scope($request, $workspace);
        $plans = Plan::where('workspace_id', $workspace->id)->where('project_id', $project?->id)->where('data->schema_version', 1)->latest()->paginate(20)->withQueryString();

        return view('plans.index', compact('workspace', 'project', 'plans'));
    }

    public function create(Request $request, Workspace $workspace)
    {
        $project = $this->scope($request, $workspace, 'plan.create');
        $base = null;
        $payload = ['schema_version' => 1, 'tasks' => [['ref' => 'work1', 'title' => 'Công việc mới', 'task_type' => 'WORK', 'priority' => 'medium', 'estimate_minutes' => 60, 'assignee_id' => $request->user()->id]], 'dependencies' => [], 'slots' => []];
        if ($request->filled('base_id')) {
            $base = Plan::findOrFail($request->input('base_id'));
            $this->bound($request, $workspace, $base);
            abort_unless($base->project_id === $project?->id, 404);
            $payload = $base->data['payload'];
            foreach ($payload['tasks'] as &$row) {
                if (isset($base->data['applied_task_ids'][$row['ref']])) {
                    $row['task_id'] = $base->data['applied_task_ids'][$row['ref']];
                }
            }unset($row);
        }
        $people = app(ProjectAccess::class)->eligibleMembers($workspace, $project);

        return view('plans.create', compact('workspace', 'project', 'base', 'payload', 'people'));
    }

    public function store(Request $request, Workspace $workspace)
    {
        $values = $request->validate(['name' => 'required|string|max:180', 'payload' => 'required|string|max:120000', 'base_id' => 'nullable|uuid']);
        $project = $this->scope($request, $workspace, 'plan.create');
        try {
            $payload = json_decode($values['payload'], true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                throw new \JsonException;
            }
        } catch (\JsonException) {
            throw ValidationException::withMessages(['payload' => 'JSON không hợp lệ.']);
        }
        $base = empty($values['base_id']) ? null : Plan::findOrFail($values['base_id']);
        $plan = $this->service->create($request->user(), $workspace, $project, $values['name'], $payload, $base);

        return to_route('workspaces.plans.show', [$workspace, $plan])->with('success', 'Đã lưu phiên bản nháp; chưa thay đổi task/lịch.');
    }

    public function show(Request $request, Workspace $workspace, Plan $plan)
    {
        $this->bound($request, $workspace, $plan);
        $project = $plan->project;
        $parent = $plan->parent;
        $impact = $this->service->impact($request->user(), $workspace, $project);

        return view('plans.show', compact('workspace', 'project', 'plan', 'parent', 'impact'));
    }

    public function action(Request $request, Workspace $workspace, Plan $plan, string $action)
    {
        $this->bound($request, $workspace, $plan);
        $v = $request->validate(['expected_status' => 'required|in:draft,proposed,approved,rejected,superseded', 'reason' => 'nullable|string|max:4000']);
        $this->service->transition($request->user(), $plan, $action, $v['expected_status'], $v['reason'] ?? null);

        return to_route('workspaces.plans.show', [$workspace, $plan])->with('success', 'Đã xử lý kế hoạch.');
    }

    public function schedule(Request $request, Workspace $workspace, Plan $plan)
    {
        $this->bound($request, $workspace, $plan);
        $v = $request->validate(['from' => 'required|date_format:Y-m-d']);
        $new = app(PlanScheduleService::class)->suggest($request->user(), $plan, $v['from']);

        return to_route('workspaces.plans.show', [$workspace, $new])->with('success', 'Đã tạo phiên bản đề xuất lịch theo thuật toán local; chưa áp dụng và không phải kết quả AI.');
    }

    public function impact(Request $request, Workspace $workspace)
    {
        $project = $this->scope($request, $workspace);
        $impact = $this->service->impact($request->user(), $workspace, $project);

        return view('plans.impact', compact('workspace', 'project', 'impact'));
    }
}
