<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Models\ReportSnapshot;
use App\Models\Workspace;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $filters = $request->validate(['project_id' => ['nullable', 'uuid'], 'created_from' => ['nullable', 'date_format:Y-m-d'], 'created_to' => ['nullable', 'date_format:Y-m-d', 'after:created_from'], 'page' => ['sometimes', 'integer', 'min:1', 'max:100000']]);
        $query = ReportSnapshot::where('workspace_id', $workspace->id)->where('generated_by', $request->user()->id)->where('metrics_json->schema_version', 1)
            ->when($filters['project_id'] ?? null, fn ($q, $id) => $q->where('project_id', $id))
            ->when($filters['created_from'] ?? null, fn ($q, $date) => $q->where('created_at', '>=', CarbonImmutable::parse($date, $request->user()->timezone)->utc()))
            ->when($filters['created_to'] ?? null, fn ($q, $date) => $q->where('created_at', '<', CarbonImmutable::parse($date, $request->user()->timezone)->utc()));
        $rows = $query->orderByDesc('created_at')->orderByDesc('id')->limit(1001)->get();
        if ($rows->count() > 1000) {
            throw ValidationException::withMessages(['created_from' => 'Có quá nhiều bản báo cáo; lọc project hoặc ngày tạo.']);
        }
        $visible = $rows->filter(fn ($snapshot) => Gate::allows('view', $snapshot))->values();
        $page = (int) ($filters['page'] ?? 1);
        $snapshots = new LengthAwarePaginator($visible->forPage($page, 20)->values(), $visible->count(), 20, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('reports.index', compact('workspace', 'snapshots', 'filters'));
    }

    public function store(ReportRequest $request, Workspace $workspace, ReportService $reports)
    {
        $snapshot = $reports->snapshot($request->user(), $workspace, $request->validated());

        return to_route('workspaces.reports.show', [$workspace, $snapshot])->with('success', 'Đã lưu bản báo cáo cố định.');
    }

    private function bound(Workspace $workspace, ReportSnapshot $snapshot): void
    {
        abort_unless($snapshot->workspace_id === $workspace->id, 404);
        Gate::authorize('view', $snapshot);
    }

    public function show(Request $request, Workspace $workspace, ReportSnapshot $snapshot)
    {
        $this->bound($workspace, $snapshot);

        return view('reports.show', compact('workspace', 'snapshot') + ['metrics' => $snapshot->metrics_json]);
    }

    public function print(Request $request, Workspace $workspace, ReportSnapshot $snapshot)
    {
        $this->bound($workspace, $snapshot);

        return view('reports.print', compact('workspace', 'snapshot') + ['metrics' => $snapshot->metrics_json]);
    }

    public function export(Request $request, Workspace $workspace, ReportSnapshot $snapshot)
    {
        $this->bound($workspace, $snapshot);
        $m = $snapshot->metrics_json;

        return response()->streamDownload(function () use ($m) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            $write = function (array $values) use ($file) {
                fputcsv($file, array_map(fn ($value) => is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value, $values), ',', '"', '');
            };
            $write(['Workspace', $m['workspace_name']]);
            $write(['Kỳ [from,to)', $m['period']['from'], $m['period']['to'], $m['timezone']]);
            $write(['Lúc tạo', $m['generated_at']]);
            foreach ($m['inventory']['status_counts'] as $status => $number) {
                $write(['WORK '.$status, $number]);
            }
            $write(['Quá hạn hiện tại', $m['inventory']['overdue']]);
            $write(['Hoàn thành trong kỳ', $m['period_metrics']['completed_work']]);
            $write(['Giờ dự kiến (phút)', $m['period_metrics']['planned_minutes']]);
            $write(['Giờ thực tế (phút)', $m['period_metrics']['actual_minutes'] ?? 'Chưa có dữ liệu']);
            $write(['Thành viên', 'WORK không hủy', 'Ước lượng phút', 'Chưa ước lượng', 'Dự kiến phút', 'Thực tế phút']);
            foreach ($m['workload'] as $person) {
                $write([$person['name'], $person['tasks'], $person['estimate_minutes'], $person['estimate_unknown'], $person['planned_minutes'], $person['actual_minutes'] ?? 'Chưa có dữ liệu']);
            }
            foreach ($m['definitions'] as $definition) {
                $write(['Cách tính', $definition]);
            }fclose($file);
        }, 'report-'.$snapshot->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
