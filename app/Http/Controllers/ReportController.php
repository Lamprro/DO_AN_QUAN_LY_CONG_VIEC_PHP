<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Models\ReportSnapshot;
use App\Models\Workspace;
use App\Services\Report\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private ReportService $query) {}

    public function index(Request $request, Workspace $workspace)
    {
        $filters = $request->validate([
            'project_id' => ['nullable', 'uuid'],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d', 'after:created_from'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ]);
        $snapshots = $this->query->snapshots($request->user(), $workspace, $filters, $request->url(), $request->query());

        return view('reports.index', compact('workspace', 'snapshots', 'filters'));
    }

    public function store(ReportRequest $request, Workspace $workspace, ReportService $reports)
    {
        $snapshot = $reports->snapshot($request->user(), $workspace, $request->validated());

        return to_route('workspaces.reports.show', [$workspace, $snapshot])->with('success', 'Đã lưu bản báo cáo cố định.');
    }

    public function show(Workspace $workspace, ReportSnapshot $snapshot)
    {
        $this->bound($workspace, $snapshot);

        return view('reports.show', compact('workspace', 'snapshot') + ['metrics' => $snapshot->metrics_json]);
    }

    public function print(Workspace $workspace, ReportSnapshot $snapshot)
    {
        $this->bound($workspace, $snapshot);

        return view('reports.print', compact('workspace', 'snapshot') + ['metrics' => $snapshot->metrics_json]);
    }

    public function export(Workspace $workspace, ReportSnapshot $snapshot)
    {
        $this->bound($workspace, $snapshot);
        $rows = $this->query->csvRows($snapshot->metrics_json);

        return response()->streamDownload(function () use ($rows) {
            $file = fopen('php://output', 'w');
            fwrite($file, '﻿');
            foreach ($rows as $values) {
                // Neutralise spreadsheet formulas in user-controlled cells.
                fputcsv($file, array_map(fn ($value) => is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value, $values), ',', '"', '');
            }
            fclose($file);
        }, 'report-'.$snapshot->id.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function bound(Workspace $workspace, ReportSnapshot $snapshot): void
    {
        abort_unless($snapshot->workspace_id === $workspace->id, 404);
        $this->authorize('view', $snapshot);
    }
}
