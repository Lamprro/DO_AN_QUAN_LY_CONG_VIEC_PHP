<?php

namespace App\Services\Report;

use App\Authorization\ReportAccess;
use App\Models\AiRun;
use App\Models\ReportSnapshot;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Workspace\WorkspaceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * History lists of the actor's own report snapshots and AI runs. Each row is
 * re-checked with its policy (the user's access may have shrunk since it was made).
 */
class ReportQuery
{
    public function __construct(private ReportService $reports, private ReportAccess $access, private WorkspaceService $workspaces) {}

    /** Live dashboard; ReportService::metrics asserts report.view on the scope. */
    public function dashboard(User $actor, Workspace $workspace, array $filters): array
    {
        $metrics = $this->reports->metrics($actor, $workspace, $filters);
        $project = empty($filters['project_id']) ? null : $workspace->projects()->findOrFail($filters['project_id']);

        return ['workspace' => $workspace, 'metrics' => $metrics, 'filters' => $filters] + $this->formOptions($actor, $workspace)
            + ['canCreate' => $this->access->scope($actor, $workspace, $project, 'report.create')];
    }

    /** Form for an AI commentary request; asserts report.create on the scope. */
    public function aiRunForm(User $actor, Workspace $workspace, array $filters): array
    {
        $this->reports->filters($actor, $workspace, $filters, 'report.create');

        return ['workspace' => $workspace, 'filters' => $filters] + $this->formOptions($actor, $workspace);
    }

    private function formOptions(User $actor, Workspace $workspace): array
    {
        return ['projects' => $this->access->projects($actor, $workspace), 'members' => $this->workspaces->activeMembers($workspace)];
    }

    public function snapshots(User $actor, Workspace $workspace, array $filters, string $path, array $query): LengthAwarePaginator
    {
        $rows = ReportSnapshot::where('workspace_id', $workspace->id)->where('generated_by', $actor->id)->where('metrics_json->schema_version', 1)
            ->when($filters['project_id'] ?? null, fn ($q, $id) => $q->where('project_id', $id));

        return $this->visiblePage($actor, $this->createdBetween($rows, $actor, $filters), $filters, $path, $query, 'Có quá nhiều bản báo cáo; lọc project hoặc ngày tạo.');
    }

    public function aiRuns(User $actor, Workspace $workspace, array $filters, string $path, array $query): LengthAwarePaginator
    {
        $rows = AiRun::where('workspace_id', $workspace->id)->where('requested_by', $actor->id)->where('context_snapshot->schema_version', 1)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));

        return $this->visiblePage($actor, $this->createdBetween($rows, $actor, $filters), $filters, $path, $query, 'Có quá nhiều yêu cầu; lọc trạng thái hoặc ngày tạo.');
    }

    /** CSV rows of a frozen snapshot (formula-leading cells are escaped by the caller). */
    public function csvRows(array $m): array
    {
        $rows = [
            ['Workspace', $m['workspace_name']],
            ['Kỳ [from,to)', $m['period']['from'], $m['period']['to'], $m['timezone']],
            ['Lúc tạo', $m['generated_at']],
        ];
        foreach ($m['inventory']['status_counts'] as $status => $number) {
            $rows[] = ['WORK '.$status, $number];
        }
        $rows[] = ['Quá hạn hiện tại', $m['inventory']['overdue']];
        $rows[] = ['Hoàn thành trong kỳ', $m['period_metrics']['completed_work']];
        $rows[] = ['Giờ dự kiến (phút)', $m['period_metrics']['planned_minutes']];
        $rows[] = ['Giờ thực tế (phút)', $m['period_metrics']['actual_minutes'] ?? 'Chưa có dữ liệu'];
        $rows[] = ['Thành viên', 'WORK không hủy', 'Ước lượng phút', 'Chưa ước lượng', 'Dự kiến phút', 'Thực tế phút'];
        foreach ($m['workload'] as $person) {
            $rows[] = [$person['name'], $person['tasks'], $person['estimate_minutes'], $person['estimate_unknown'], $person['planned_minutes'], $person['actual_minutes'] ?? 'Chưa có dữ liệu'];
        }
        foreach ($m['definitions'] as $definition) {
            $rows[] = ['Cách tính', $definition];
        }

        return $rows;
    }

    private function createdBetween(Builder $rows, User $actor, array $filters): Builder
    {
        return $rows->when($filters['created_from'] ?? null, fn ($q, $date) => $q->where('created_at', '>=', CarbonImmutable::parse($date, $actor->timezone)->utc()))
            ->when($filters['created_to'] ?? null, fn ($q, $date) => $q->where('created_at', '<', CarbonImmutable::parse($date, $actor->timezone)->utc()));
    }

    private function visiblePage(User $actor, Builder $rows, array $filters, string $path, array $query, string $tooMany): LengthAwarePaginator
    {
        $rows = $rows->orderByDesc('created_at')->orderByDesc('id')->limit(1001)->get();
        if ($rows->count() > 1000) {
            throw ValidationException::withMessages(['created_from' => $tooMany]);
        }
        $visible = $rows->filter(fn ($row) => Gate::forUser($actor)->allows('view', $row))->values();
        $page = (int) ($filters['page'] ?? 1);

        return new LengthAwarePaginator($visible->forPage($page, 20)->values(), $visible->count(), 20, $page, ['path' => $path, 'query' => $query]);
    }
}
