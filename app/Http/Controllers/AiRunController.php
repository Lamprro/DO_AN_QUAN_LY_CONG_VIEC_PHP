<?php

namespace App\Http\Controllers;

use App\Enums\AiRunStatus;
use App\Http\Requests\AiRunRequest;
use App\Http\Requests\ReportRequest;
use App\Models\AiRun;
use App\Models\Workspace;
use App\Services\AiRunService;
use App\Services\ReportService;
use App\Support\ReportAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AiRunController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $filters = $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:100000'], 'status' => ['nullable', Rule::enum(AiRunStatus::class)],
            'created_from' => ['nullable', 'date_format:Y-m-d'], 'created_to' => ['nullable', 'date_format:Y-m-d', 'after:created_from']]);
        $rows = AiRun::where('workspace_id', $workspace->id)->where('requested_by', $request->user()->id)->where('context_snapshot->schema_version', 1)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['created_from'] ?? null, fn ($q, $date) => $q->where('created_at', '>=', CarbonImmutable::parse($date, $request->user()->timezone)->utc()))
            ->when($filters['created_to'] ?? null, fn ($q, $date) => $q->where('created_at', '<', CarbonImmutable::parse($date, $request->user()->timezone)->utc()))
            ->orderByDesc('created_at')->orderByDesc('id')->limit(1001)->get();
        if ($rows->count() > 1000) {
            throw ValidationException::withMessages(['created_from' => 'Có quá nhiều yêu cầu; lọc trạng thái hoặc ngày tạo.']);
        }
        $visible = $rows->filter(fn ($run) => Gate::allows('view', $run))->values();
        $page = (int) ($filters['page'] ?? 1);
        $runs = new LengthAwarePaginator($visible->forPage($page, 20)->values(), $visible->count(), 20, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('ai-runs.index', compact('workspace', 'runs', 'filters'));
    }

    public function create(ReportRequest $request, Workspace $workspace, ReportAccess $access)
    {
        $data = $request->validated();
        app(ReportService::class)->filters($request->user(), $workspace, $data, 'report.create');

        return view('ai-runs.create', ['workspace' => $workspace, 'filters' => $data, 'projects' => $access->projects($request->user(), $workspace), 'members' => $workspace->members()->where('status', 'active')->whereHas('user', fn ($q) => $q->where('status', 'active'))->with('user')->get(), 'token' => (string) Str::uuid()]);
    }

    public function store(AiRunRequest $request, Workspace $workspace, AiRunService $service)
    {
        $run = $service->request($request->user(), $workspace, $request->validated());

        return to_route('workspaces.ai-runs.show', [$workspace, $run])->with('success', $run->status->value === 'queued' ? 'Đã lưu yêu cầu vào hàng đợi.' : 'Yêu cầu đã tồn tại; đang hiển thị trạng thái hiện tại.');
    }

    private function bound(Workspace $workspace, AiRun $run): void
    {
        abort_unless($run->workspace_id === $workspace->id, 404);
        Gate::authorize('view', $run);
    }

    public function show(Request $request, Workspace $workspace, AiRun $run)
    {
        $this->bound($workspace, $run);
        $calls = $run->calls()->orderBy('attempt_no')->get();

        return view('ai-runs.show', compact('workspace', 'run', 'calls'));
    }

    public function cancel(Request $request, Workspace $workspace, AiRun $run, AiRunService $service)
    {
        $this->bound($workspace, $run);
        $data = $request->validate(['expected_status' => ['required', 'in:queued,running']]);
        $service->action($request->user(), $run, 'cancel', $data['expected_status']);

        return back()->with('success', 'Đã hủy yêu cầu.');
    }

    public function retry(Request $request, Workspace $workspace, AiRun $run, AiRunService $service)
    {
        $this->bound($workspace, $run);
        $data = $request->validate(['expected_status' => ['required', 'in:failed']]);
        $service->action($request->user(), $run, 'retry', $data['expected_status']);

        return back()->with('success', 'Đã đưa yêu cầu vào hàng đợi thử lại.');
    }
}
