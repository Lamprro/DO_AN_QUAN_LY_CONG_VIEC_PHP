<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Project;
use App\Models\Workspace;
use App\Services\Planning\PlanPageData;
use App\Services\Planning\PlanReviewService;
use App\Services\Planning\PlanScheduleService;
use App\Services\Planning\PlanService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PlanController extends Controller
{
    public function __construct(private PlanService $service, private PlanPageData $pages) {}

    public function index(Request $request, Workspace $workspace)
    {
        return view('plans.index', $this->pages->index($request->user(), $workspace, $this->project($request, $workspace)));
    }

    public function create(Request $request, Workspace $workspace)
    {
        $request->validate([
            'source_q' => 'nullable|string|max:200',
            'source_selected' => 'nullable|array|max:100',
            'source_selected.*' => 'uuid',
        ]);
        $project = $this->project($request, $workspace);
        $data = $this->pages->form($request->user(), $workspace, $project, $request->input('base_id'), $request->session()->getOldInput(), $request->query('source_q'), $request->query('source_selected', []));
        if (isset($data['invalidBase'])) {
            return to_route('workspaces.plans.show', [$workspace, $data['invalidBase']])->with('warning', 'Kế hoạch nguồn thiếu dữ liệu chuẩn. Hãy tạo kế hoạch mới thay vì sao chép bản này.');
        }

        return view('plans.create', $data);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $project = $this->project($request, $workspace);
        $mode = $request->input('input_mode', 'json');
        $request->validate([
            'input_mode' => 'sometimes|in:editor,json',
            'name' => 'required|string|max:180',
            'base_id' => 'nullable|uuid',
            'payload' => 'nullable|string|max:120000',
            'json_payload' => 'nullable|string|max:120000',
            'editor' => 'nullable|array',
        ]);
        if ($mode === 'editor' && $request->input('editor_enabled') === '0') {
            throw ValidationException::withMessages([
                'input_mode' => 'Bật JavaScript hoặc chọn JSON nâng cao để giữ đầy đủ dữ liệu kế hoạch.',
            ]);
        }
        $values = $request->only('name', 'base_id');
        try {
            if ($mode === 'editor' && ! $request->filled('payload')) {
                $payload = $this->pages->normalize($request->input('editor', []));
                $request->merge([
                    'editor' => [
                        'tasks' => $payload['tasks'],
                        'dependencies' => $payload['dependencies'],
                        'slots' => $payload['slots'],
                    ],
                ]);
            } else {
                $payload = json_decode($request->filled('payload') ? $request->input('payload') : $request->input('json_payload', ''), true, 32, JSON_THROW_ON_ERROR);
                if (! is_array($payload)) {
                    throw new \JsonException;
                }
            }
            $base = empty($values['base_id']) ? null : Plan::findOrFail($values['base_id']);
            $plan = $this->service->create($request->user(), $workspace, $project, $values['name'], $payload, $base);
        } catch (\JsonException) {
            throw ValidationException::withMessages([
                'payload' => 'Nội dung JSON không hợp lệ. Chuyển về trình soạn hoặc kiểm tra JSON nâng cao.',
            ]);
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
        $this->pages->assertPlan($request->user(), $workspace, $plan);

        return view('plans.show', $this->pages->show($request->user(), $workspace, $plan));
    }

    public function preview(Request $request, Workspace $workspace, Plan $plan, PlanReviewService $review)
    {
        $this->pages->assertPlan($request->user(), $workspace, $plan);

        return view('plans.preview', [
            'workspace' => $workspace,
            'project' => $plan->project,
            'plan' => $plan,
            'preview' => $review->preview($request->user(), $plan),
        ]);
    }

    public function action(Request $request, Workspace $workspace, Plan $plan, string $action)
    {
        $this->pages->assertPlan($request->user(), $workspace, $plan);
        $validated = $request->validate([
            'expected_status' => 'required|in:draft,proposed,approved,rejected,superseded',
            'reason' => 'nullable|string|max:4000',
        ]);
        $this->service->transition($request->user(), $plan, $action, $validated['expected_status'], $validated['reason'] ?? null);

        return to_route('workspaces.plans.show', [$workspace, $plan])->with('success', 'Đã xử lý kế hoạch.');
    }

    public function schedule(Request $request, Workspace $workspace, Plan $plan, PlanScheduleService $scheduler)
    {
        $this->pages->assertPlan($request->user(), $workspace, $plan);
        $validated = $request->validate(['from' => 'required|date_format:Y-m-d']);
        $new = $scheduler->suggest($request->user(), $plan, $validated['from']);

        return to_route('workspaces.plans.show', [$workspace, $new])->with('success', 'Đã tạo phiên bản đề xuất lịch theo thuật toán local; chưa áp dụng và không phải kết quả AI.');
    }

    public function impact(Request $request, Workspace $workspace)
    {
        $project = $this->project($request, $workspace);

        return view('plans.impact', [
            'workspace' => $workspace,
            'project' => $project,
            'impact' => $this->service->impact($request->user(), $workspace, $project),
        ]);
    }

    /** Optional ?project_id= scope, which must belong to the workspace. */
    private function project(Request $request, Workspace $workspace): ?Project
    {
        $request->validate(['project_id' => 'nullable|uuid']);

        return $request->filled('project_id') ? $workspace->projects()->findOrFail($request->input('project_id')) : null;
    }
}
