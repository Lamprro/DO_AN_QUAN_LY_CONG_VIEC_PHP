<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\Ai\AiRunService;
use App\Services\Planning\PlanQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlanningAiController extends Controller
{
    public function create(Request $r, Workspace $workspace, PlanQuery $plans)
    {
        $r->validate(['project_id' => 'nullable|uuid']);
        $project = $r->filled('project_id') ? $workspace->projects()->findOrFail($r->input('project_id')) : null;
        $bases = $plans->bases($r->user(), $workspace, $project);

        return view('plans.ai', ['workspace' => $workspace, 'project' => $project, 'bases' => $bases, 'token' => (string) Str::uuid()]);
    }

    public function store(Request $r, Workspace $workspace, AiRunService $service)
    {
        $v = $r->validate(['project_id' => 'nullable|uuid', 'base_id' => 'nullable|uuid', 'operation' => 'required|in:planning,replanning,assistance', 'user_prompt' => 'required|string|max:4000', 'idempotency_token' => 'required|uuid']);
        $run = $service->requestPlanning($r->user(), $workspace, $v);

        return to_route('workspaces.ai-runs.show', [$workspace, $run])->with('success', 'Đã lưu yêu cầu AI. Kết quả kế hoạch chỉ là nháp, chưa áp dụng.');
    }
}
