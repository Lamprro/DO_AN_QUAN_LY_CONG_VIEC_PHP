<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\Workspace;
use App\Policies\CalendarEventPolicy;
use App\Policies\TaskPolicy;
use App\Support\ProjectAccess;
use App\Support\ReportAccess;
use App\Support\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AppShell
{
    public function build(Request $request): array
    {
        $actor = $request->user();
        $shell = ['workspaces' => collect(), 'workspace' => null, 'project' => null, 'menu' => [], 'breadcrumbs' => [], 'timer' => null, 'timerTaskVisible' => false];
        if (! $actor) {
            return $shell;
        }
        $access = app(WorkspaceAccess::class);
        $projects = app(ProjectAccess::class);
        $shell['workspaces'] = Workspace::whereHas('members', fn ($q) => $q->where('user_id', $actor->id)->where('status', 'active'))->orderBy('name')->get()->filter(fn ($w) => $access->allows($actor, $w, 'workspace.view'));
        $workspace = $request->route('workspace');
        if (! $workspace instanceof Workspace || ! $access->allows($actor, $workspace, 'workspace.view')) {
            $workspace = null;
        }
        $project = $request->route('project') ?? $request->attributes->get('scope_project');
        if (! $project instanceof Project && $workspace) {
            $plan = $request->route('plan');
            $query = $request->query('project_id');
            if ($plan instanceof Plan && $plan->workspace_id === $workspace->id) {
                $project = $plan->project;
            } elseif (is_string($query) && Str::isUuid($query)) {
                $project = $workspace->projects()->find($query);
            }
        }
        if (! $project instanceof Project || ! $workspace || $project->workspace_id !== $workspace->id || ! $projects->allows($actor, $project, 'project.view')) {
            $project = null;
        }
        $shell['workspace'] = $workspace;
        $shell['project'] = $project;
        $menu = [];
        $currentRoute = $request->route()?->getName() ?? '';
        $add = function ($label, $route, $params = []) use (&$menu, $currentRoute, $project, $workspace) {
            $prefix = Str::beforeLast($route, '.');
            $score = $currentRoute === $route ? 1000 + strlen($route)
                : (str_ends_with($route, '.index') && str_starts_with($currentRoute, $prefix.'.') ? strlen($prefix) : 0);
            $group = match (true) {
                ! str_starts_with($route, 'workspaces.') || in_array($route, ['workspaces.index', 'workspaces.operations', 'workspaces.reminders'], true) => 'Cá nhân',
                in_array($route, ['workspaces.show', 'workspaces.projects.index', 'workspaces.members.index', 'workspaces.roles.index'], true) => 'Không gian làm việc',
                default => $project ? 'Dự án hiện tại' : ($workspace?->kind->value === 'personal' ? 'Công việc cá nhân' : 'Không gian làm việc'),
            };
            $menu[] = ['label' => $label, 'url' => route($route, $params), 'active' => false, 'group' => $group, 'score' => $score];
        };
        $add('Không gian làm việc', 'workspaces.index');
        $add('Giờ thực tế của tôi', 'timers.index');
        $add('Thông báo', 'notifications.index');
        $add('Giờ làm việc', 'work-hours.edit');
        $crumb = [['label' => 'Workspace', 'url' => route('workspaces.index')]];
        if ($workspace) {
            $crumb[] = ['label' => $workspace->name, 'url' => route('workspaces.show', $workspace)];
            if ($access->allows($actor, $workspace, 'workspace.view_operations')) {
                $add('Vận hành của tôi', 'workspaces.operations', $workspace);
            }
            $add('Nhắc việc của tôi', 'workspaces.reminders', $workspace);
            $add('Tổng quan không gian', 'workspaces.show', $workspace);
            $add('Dự án', 'workspaces.projects.index', $workspace);
            if ($access->allows($actor, $workspace, 'workspace.manage_members')) {
                $add('Thành viên không gian', 'workspaces.members.index', $workspace);
            }
            if ($access->allows($actor, $workspace, 'workspace.manage_roles')) {
                $add('Vai trò', 'workspaces.roles.index', $workspace);
            }
            if ($project) {
                $crumb[] = ['label' => $project->name, 'url' => route('workspaces.projects.show', [$workspace, $project])];
                $add('Tổng quan dự án', 'workspaces.projects.show', [$workspace, $project]);
            }
            if ($project || $workspace->kind->value === 'personal') {
                $params = $project ? [$workspace, $project] : [$workspace];
                $prefix = $project ? 'workspaces.projects.tasks' : 'workspaces.tasks';
                if (app(TaskPolicy::class)->scope($actor, $workspace, $project, 'task.view')) {
                    $add('Công việc', $prefix.'.index', $params);
                    $add('Cây công việc', $prefix.'.tree', $params);
                    $add('Mẫu / công việc lặp', 'workspaces.templates.index', [$workspace, 'project_id' => $project?->id]);
                }
                $scope = [$workspace, 'project_id' => $project?->id];
                if (app(PlanService::class)->allowed($actor, $workspace, $project)) {
                    $add('Kế hoạch', 'workspaces.plans.index', $scope);
                    $add('Ảnh hưởng công việc', 'workspaces.plans.impact', $scope);
                }
                if (app(CalendarEventPolicy::class)->scope($actor, $workspace, $project, 'calendar.view')) {
                    $add('Lịch', 'workspaces.calendar.index', $scope);
                    $add('Lịch nhóm / lời mời', 'workspaces.calendar.team', $scope);
                    if ($project) {
                        $add('Timeline', 'workspaces.projects.timeline', [$workspace, $project]);
                    }
                }
                if (app(ReportAccess::class)->scope($actor, $workspace, $project)) {
                    $add('Dashboard / báo cáo', 'workspaces.dashboard', $scope);
                }
                if ($project && $projects->allows($actor, $project, 'project.manage_members')) {
                    $add('Thành viên dự án', 'workspaces.projects.members.index', [$workspace, $project]);
                }
                if ($project && $projects->allows($actor, $project, 'integration.view')) {
                    $add('Nhật ký GitHub', 'workspaces.projects.github.show', [$workspace, $project]);
                }
            } elseif (app(ReportAccess::class)->scope($actor, $workspace, null)) {
                $add('Dashboard workspace', 'workspaces.dashboard', $workspace);
            }
        }
        $bestScore = max(array_column($menu, 'score'));
        foreach ($menu as &$item) {
            $item['active'] = $bestScore > 0 && $item['score'] === $bestScore;
        }
        unset($item);
        $task = $request->route('task');
        if ($task instanceof Task && $actor->can('view', $task)) {
            $crumb[] = ['label' => 'Công việc', 'url' => route($project ? 'workspaces.projects.tasks.index' : 'workspaces.tasks.index', $project ? [$workspace, $project] : [$workspace])];
            $crumb[] = ['label' => $task->code, 'url' => null];
        }
        $plan = $request->route('plan');
        if ($plan instanceof Plan && $workspace && app(PlanService::class)->allowed($actor, $workspace, $project)) {
            $crumb[] = ['label' => $plan->name.' · v'.$plan->version_no, 'url' => null];
        }
        $timer = TimeEntry::where('user_id', $actor->id)->whereNull('ended_at')->with('task')->first();
        $shell['timer'] = $timer;
        $shell['timerTaskVisible'] = $timer && $actor->can('view', $timer->task);

        return array_replace($shell, ['menu' => $menu, 'breadcrumbs' => $crumb]);
    }
}
