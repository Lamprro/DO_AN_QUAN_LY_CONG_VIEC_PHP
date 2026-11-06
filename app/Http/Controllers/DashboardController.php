<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Models\Workspace;
use App\Services\Report\ReportQuery;

class DashboardController extends Controller
{
    public function index(ReportRequest $request, Workspace $workspace, ReportQuery $reports)
    {
        return view('reports.dashboard', $reports->dashboard($request->user(), $workspace, $request->validated()));
    }
}
