<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Models\Workspace;
use App\Services\ReportService;
use App\Support\ReportAccess;

class DashboardController extends Controller
{
    public function index(ReportRequest $request, Workspace $workspace, ReportService $reports, ReportAccess $access)
    {
        $data = $request->validated();
        $metrics = $reports->metrics($request->user(), $workspace, $data);
        $project = empty($data['project_id']) ? null : $workspace->projects()->findOrFail($data['project_id']);

        return view('reports.dashboard', ['workspace' => $workspace, 'metrics' => $metrics, 'filters' => $data, 'projects' => $access->projects($request->user(), $workspace),
            'members' => $workspace->members()->where('status', 'active')->whereHas('user', fn ($q) => $q->where('status', 'active'))->with('user')->get(),
            'canCreate' => $access->scope($request->user(), $workspace, $project, 'report.create')]);
    }
}
