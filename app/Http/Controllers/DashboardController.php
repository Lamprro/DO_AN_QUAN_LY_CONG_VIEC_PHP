<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Models\Workspace;
use App\Services\Report\ReportService;

class DashboardController extends Controller
{
    public function index(ReportRequest $request, Workspace $workspace, ReportService $reports)
    {
        return view('reports.dashboard', $reports->dashboard($request->user(), $workspace, $request->validated()));
    }
}
