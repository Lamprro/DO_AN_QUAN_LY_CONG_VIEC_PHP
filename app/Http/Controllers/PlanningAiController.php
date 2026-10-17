<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Workspace;
use App\Services\AiRunService;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlanningAiController extends Controller
{
    public function create(Request $r, Workspace $workspace)
    {
        $r->validate(['project_id' => 'nullable|uuid']);
        $project = $r->filled('project_id') ? $workspace->projects()->findOrFail($r->input('project_id')) : null;
        abort_unless(app(PlanService::class)->allowed($r->user(), $workspace, $project, 'plan.create'), 403);
        $bases = Plan::where('workspace_id', $workspace->id)->where('project_id', $project?->id)->where('data->schema_version', 1)->latest()->limit(100)->get();
        $token = (string) Str::uuid();

        return view('plans.ai', compact('workspace', 'project', 'bases', 'token'));
    }

    public function store(Request $r, Workspace $workspace, AiRunService $service)
    {
        $v = $r->validate(['project_id' => 'nullable|uuid', 'base_id' => 'nullable|uuid', 'operation' => 'required|in:planning,replanning,assistance', 'user_prompt' => 'required|string|max:4000', 'idempotency_token' => 'required|uuid']);
        $run = $service->requestPlanning($r->user(), $workspace, $v);

        return to_route('workspaces.ai-runs.show', [$workspace, $run])->with('success', 'Đã lưu yêu cầu AI. Kết quả kế hoạch chỉ là nháp, chưa áp dụng.');
    }
}
