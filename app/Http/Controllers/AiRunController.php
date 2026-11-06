<?php

namespace App\Http\Controllers;

use App\Enums\AiRunStatus;
use App\Http\Requests\AiRunRequest;
use App\Http\Requests\ReportRequest;
use App\Models\AiRun;
use App\Models\Workspace;
use App\Services\Ai\AiRunService;
use App\Services\Report\ReportQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AiRunController extends Controller
{
    public function __construct(private AiRunService $service) {}

    public function index(Request $request, Workspace $workspace, ReportQuery $query)
    {
        $filters = $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:100000'], 'status' => ['nullable', Rule::enum(AiRunStatus::class)],
            'created_from' => ['nullable', 'date_format:Y-m-d'], 'created_to' => ['nullable', 'date_format:Y-m-d', 'after:created_from']]);
        $runs = $query->aiRuns($request->user(), $workspace, $filters, $request->url(), $request->query());

        return view('ai-runs.index', compact('workspace', 'runs', 'filters'));
    }

    /** Report-commentary request form. */
    public function create(ReportRequest $request, Workspace $workspace, ReportQuery $reports)
    {
        return view('ai-runs.create', $reports->aiRunForm($request->user(), $workspace, $request->validated()) + ['token' => (string) Str::uuid()]);
    }

    public function store(AiRunRequest $request, Workspace $workspace)
    {
        $run = $this->service->request($request->user(), $workspace, $request->validated());

        return to_route('workspaces.ai-runs.show', [$workspace, $run])->with('success', $run->status->value === 'queued' ? 'Đã lưu yêu cầu vào hàng đợi.' : 'Yêu cầu đã tồn tại; đang hiển thị trạng thái hiện tại.');
    }

    public function show(Workspace $workspace, AiRun $run)
    {
        $this->bound($workspace, $run);
        $calls = $run->calls()->orderBy('attempt_no')->get();

        return view('ai-runs.show', compact('workspace', 'run', 'calls'));
    }

    public function cancel(Request $request, Workspace $workspace, AiRun $run)
    {
        abort_unless($run->workspace_id === $workspace->id, 404);
        $data = $request->validate(['expected_status' => ['required', 'in:queued,running']]);
        $this->service->action($request->user(), $run, 'cancel', $data['expected_status']);

        return back()->with('success', 'Đã hủy yêu cầu.');
    }

    public function retry(Request $request, Workspace $workspace, AiRun $run)
    {
        abort_unless($run->workspace_id === $workspace->id, 404);
        $data = $request->validate(['expected_status' => ['required', 'in:failed']]);
        $this->service->action($request->user(), $run, 'retry', $data['expected_status']);

        return back()->with('success', 'Đã đưa yêu cầu vào hàng đợi thử lại.');
    }

    private function bound(Workspace $workspace, AiRun $run): void
    {
        abort_unless($run->workspace_id === $workspace->id, 404);
        $this->authorize('view', $run);
    }
}
