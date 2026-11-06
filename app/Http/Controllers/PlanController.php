<?php

namespace App\Http\Controllers;

use App\Authorization\ProjectAccess;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Workspace;
use App\Services\Planning\PlanEditorData;
use App\Services\Planning\PlanQuery;
use App\Services\Planning\PlanReviewService;
use App\Services\Planning\PlanScheduleService;
use App\Services\Planning\PlanService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PlanController extends Controller
{
    public function __construct(private PlanService $service, private PlanQuery $query, private PlanEditorData $editor) {}

    public function index(Request $request, Workspace $workspace)
    {
        return view('plans.index', $this->query->index($request->user(), $workspace, $this->project($request, $workspace)));
    }

    public function create(Request $request, Workspace $workspace, ProjectAccess $projects)
    {
        $request->validate(['source_q' => 'nullable|string|max:200', 'source_selected' => 'nullable|array|max:100', 'source_selected.*' => 'uuid']);
        $project = $this->project($request, $workspace);
        $this->query->assertScope($request->user(), $workspace, $project, 'plan.create');
        $base = null;
        $payload = ['schema_version' => 1, 'tasks' => [['ref' => 'work1', 'title' => 'Công việc mới', 'task_type' => 'WORK', 'priority' => 'medium', 'estimate_minutes' => 60, 'assignee_id' => $request->user()->id]], 'dependencies' => [], 'slots' => []];
        if ($request->filled('base_id')) {
            $base = Plan::findOrFail($request->input('base_id'));
            $this->query->assertPlan($request->user(), $workspace, $base);
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
            }
            unset($row);
        }
        // Re-display the user's draft after a validation error.
        if ($request->old('input_mode') === 'editor') {
            $old = $request->old('payload');
            if (is_string($old) && mb_strlen($old) <= 120000) {
                $decoded = json_decode($old, true, 32);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            } elseif (is_array($request->old('editor'))) {
                $payload = $this->editor->normalize($request->old('editor'));
            }
        }
        $editorData = $this->editor->build($request->user(), $workspace, $project, $payload, $request->query('source_q'), $request->query('source_selected', []));
        if (is_string($request->old('payload')) && mb_strlen($request->old('payload')) > 120000) {
            $editorData['renderable'] = false;
        }
        $people = $projects->eligibleMembers($workspace, $project);

        return view('plans.create', compact('workspace', 'project', 'base', 'payload', 'people', 'editorData'));
    }

    public function store(Request $request, Workspace $workspace)
    {
        $project = $this->project($request, $workspace);
        $mode = $request->input('input_mode', 'json');
        $request->validate(['input_mode' => 'sometimes|in:editor,json', 'name' => 'required|string|max:180', 'base_id' => 'nullable|uuid', 'payload' => 'nullable|string|max:120000', 'json_payload' => 'nullable|string|max:120000', 'editor' => 'nullable|array']);
        if ($mode === 'editor' && $request->input('editor_enabled') === '0') {
            throw ValidationException::withMessages(['input_mode' => 'Bật JavaScript hoặc chọn JSON nâng cao để giữ đầy đủ dữ liệu kế hoạch.']);
        }
        $values = $request->only('name', 'base_id');
        try {
            if ($mode === 'editor' && ! $request->filled('payload')) {
                $payload = $this->editor->normalize($request->input('editor', []));
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
        $this->query->assertPlan($request->user(), $workspace, $plan);

        return view('plans.show', $this->query->show($request->user(), $workspace, $plan));
    }

    public function preview(Request $request, Workspace $workspace, Plan $plan, PlanReviewService $review)
    {
        $this->query->assertPlan($request->user(), $workspace, $plan);

        return view('plans.preview', ['workspace' => $workspace, 'project' => $plan->project, 'plan' => $plan, 'preview' => $review->preview($request->user(), $plan)]);
    }

    public function action(Request $request, Workspace $workspace, Plan $plan, string $action)
    {
        $this->query->assertPlan($request->user(), $workspace, $plan);
        $v = $request->validate(['expected_status' => 'required|in:draft,proposed,approved,rejected,superseded', 'reason' => 'nullable|string|max:4000']);
        $this->service->transition($request->user(), $plan, $action, $v['expected_status'], $v['reason'] ?? null);

        return to_route('workspaces.plans.show', [$workspace, $plan])->with('success', 'Đã xử lý kế hoạch.');
    }

    public function schedule(Request $request, Workspace $workspace, Plan $plan, PlanScheduleService $scheduler)
    {
        $this->query->assertPlan($request->user(), $workspace, $plan);
        $v = $request->validate(['from' => 'required|date_format:Y-m-d']);
        $new = $scheduler->suggest($request->user(), $plan, $v['from']);

        return to_route('workspaces.plans.show', [$workspace, $new])->with('success', 'Đã tạo phiên bản đề xuất lịch theo thuật toán local; chưa áp dụng và không phải kết quả AI.');
    }

    public function impact(Request $request, Workspace $workspace)
    {
        $project = $this->project($request, $workspace);

        return view('plans.impact', ['workspace' => $workspace, 'project' => $project, 'impact' => $this->service->impact($request->user(), $workspace, $project)]);
    }

    /** Optional ?project_id= scope, which must belong to the workspace. */
    private function project(Request $request, Workspace $workspace): ?Project
    {
        $request->validate(['project_id' => 'nullable|uuid']);

        return $request->filled('project_id') ? $workspace->projects()->findOrFail($request->input('project_id')) : null;
    }
}
