<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Project;
use App\Models\Workspace;
use App\Policies\TaskPolicy;
use App\Services\PlanEditorData;
use App\Services\PlanReviewService;
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
        $request->validate(['source_q' => 'nullable|string|max:200', 'source_selected' => 'nullable|array|max:100', 'source_selected.*' => 'uuid']);
        $project = $this->scope($request, $workspace, 'plan.create');
        $base = null;
        $payload = ['schema_version' => 1, 'tasks' => [['ref' => 'work1', 'title' => 'Công việc mới', 'task_type' => 'WORK', 'priority' => 'medium', 'estimate_minutes' => 60, 'assignee_id' => $request->user()->id]], 'dependencies' => [], 'slots' => []];
        if ($request->filled('base_id')) {
            $base = Plan::findOrFail($request->input('base_id'));
            $this->bound($request, $workspace, $base);
            abort_unless($base->project_id === $project?->id, 404);
            try {
                $payload = $this->service->payload($base);
            } catch (ValidationException $e) {
                return to_route('workspaces.plans.show', [$workspace, $base])->with('warning', 'Kế hoạch nguồn thiếu dữ liệu chuẩn. Hãy tạo kế hoạch mới thay vì sao chép bản này.');
            }
            foreach ($payload['tasks'] as &$row) {
                if (isset($base->data['applied_task_ids'][$row['ref']])) {
                    $row['task_id'] = $base->data['applied_task_ids'][$row['ref']];
                }
            }unset($row);
        }
        $people = app(ProjectAccess::class)->eligibleMembers($workspace, $project);
        $editorService = app(PlanEditorData::class);
        if ($request->old('input_mode') === 'editor') {
            $old = $request->old('payload');
            if (is_string($old) && mb_strlen($old) <= 120000) {
                $decoded = json_decode($old, true, 32);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            } elseif (is_array($request->old('editor'))) {
                $payload = $editorService->normalize($request->old('editor'));
            }
        }
        $editorData = $editorService->build($request->user(), $workspace, $project, $payload);
        if (is_string($request->old('payload')) && mb_strlen($request->old('payload')) > 120000) {
            $editorData['renderable'] = false;
        }

        return view('plans.create', compact('workspace', 'project', 'base', 'payload', 'people', 'editorData'));
    }

    public function store(Request $request, Workspace $workspace)
    {
        $project = $this->scope($request, $workspace, 'plan.create');
        $mode = $request->input('input_mode', 'json');
        $request->validate(['input_mode' => 'sometimes|in:editor,json', 'name' => 'required|string|max:180', 'base_id' => 'nullable|uuid', 'payload' => 'nullable|string|max:120000', 'json_payload' => 'nullable|string|max:120000', 'editor' => 'nullable|array']);
        if ($mode === 'editor' && $request->input('editor_enabled') === '0') {
            throw ValidationException::withMessages(['input_mode' => 'Bật JavaScript hoặc chọn JSON nâng cao để giữ đầy đủ dữ liệu kế hoạch.']);
        }
        $values = $request->only('name', 'base_id');
        try {
            if ($mode === 'editor' && ! $request->filled('payload')) {
                $payload = app(PlanEditorData::class)->normalize($request->input('editor', []));
                $request->merge(['editor' => ['tasks' => $payload['tasks'], 'dependencies' => $payload['dependencies'], 'slots' => $payload['slots']]]);
            } else {
                $payload = json_decode($request->filled('payload') ? $request->input('payload') : $request->input('json_payload', ''), true, 32, JSON_THROW_ON_ERROR);
                if (! is_array($payload)) {
                    throw new \JsonException;
                }
            }
            $base = empty($values['base_id']) ? null : Plan::findOrFail($values['base_id']);
            $plan = $this->service->create($request->user(), $workspace, $project, $values['name'], $payload, $base);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['payload' => 'Nội dung JSON không hợp lệ. Chuyển về trình soạn hoặc kiểm tra JSON nâng cao.']);
        } catch (ValidationException $e) {
            if ($mode !== 'editor') {
                throw $e;
            }
            $mapped = [];
            foreach ($e->errors() as $key => $messages) {
                $mapped[preg_match('/^(tasks|dependencies|slots)(\.|$)/', $key) ? 'editor.'.$key : $key] = $messages;
            }
            throw ValidationException::withMessages($mapped);
        }

        return to_route('workspaces.plans.show', [$workspace, $plan])->with('success', 'Đã lưu phiên bản nháp; chưa thay đổi task/lịch.');
    }

    public function show(Request $request, Workspace $workspace, Plan $plan)
    {
        $this->bound($request, $workspace, $plan);
        $project = $plan->project;
        $parent = $plan->parent;
        $impact = $this->service->impact($request->user(), $workspace, $project);

        $diff = app(PlanReviewService::class)->diff($plan);
        $canPreview = $this->service->allowed($request->user(), $workspace, $project, 'plan.apply') && $this->service->allowed($request->user(), $workspace, $project, 'plan.approve');
        $canRevise = $this->service->allowed($request->user(), $workspace, $project, 'plan.create') && $this->service->allowed($request->user(), $workspace, $project, 'plan.update') && app(TaskPolicy::class)->projectWritable($project);
        $canSchedule = $canRevise;
        $actions = [];
        foreach (['propose' => ['draft', 'plan.update', 'Đề xuất duyệt'], 'approve' => ['proposed', 'plan.approve', 'Duyệt kế hoạch'], 'reject' => ['proposed', 'plan.approve', 'Từ chối'], 'apply' => ['approved', 'plan.apply', 'Áp dụng']] as $action => [$status,$permission,$label]) {
            if ($plan->status->value === $status && ! $plan->applied_at && app(TaskPolicy::class)->projectWritable($project) && $this->service->allowed($request->user(), $workspace, $project, $permission) && ($action !== 'apply' || $canPreview)) {
                $actions[$action] = $label;
            }
        }
        $users = app(ProjectAccess::class)->eligibleMembers($workspace, $project)->mapWithKeys(fn ($m) => [$m->user_id => $m->user->name])->all();

        return view('plans.show', compact('workspace', 'project', 'plan', 'parent', 'impact', 'diff', 'canPreview', 'canRevise', 'canSchedule', 'actions', 'users'));
    }

    public function preview(Request $request, Workspace $workspace, Plan $plan)
    {
        $this->bound($request, $workspace, $plan);
        $preview = app(PlanReviewService::class)->preview($request->user(), $plan);

        return view('plans.preview', ['workspace' => $workspace, 'project' => $plan->project, 'plan' => $plan, 'preview' => $preview]);
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
