<?php

use App\Models\AiCall;
use App\Models\AiRun;
use App\Models\CalendarEvent;
use App\Models\CalendarProposal;
use App\Models\GithubLog;
use App\Models\IntegrationConnection;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ReportSnapshot;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskBlocker;
use App\Models\TaskDependency;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateRun;
use App\Models\TaskWatcher;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

return [
    'models' => [CalendarProposal::class, TaskWatcher::class, TaskTemplate::class, TaskTemplateRun::class, User::class, UserSetting::class, Workspace::class, Role::class, WorkspaceMember::class, IntegrationConnection::class, Project::class, ProjectMember::class, Task::class, TaskDependency::class, TaskBlocker::class, AiRun::class, AiCall::class, Plan::class, CalendarEvent::class, TimeEntry::class, TaskActivity::class, Notification::class, ReportSnapshot::class, GithubLog::class],
    'references' => [
        'calendar_proposals' => ['workspace_id' => 'workspaces', 'project_id' => 'projects', 'task_id' => 'tasks', 'proposed_by' => 'users', 'recipient_id' => 'users', 'calendar_event_id' => 'calendar_events'],
        'task_watchers' => ['task_id' => 'tasks', 'user_id' => 'users', 'added_by' => 'users'],
        'task_templates' => ['workspace_id' => 'workspaces', 'project_id' => 'projects', 'created_by' => 'users'],
        'task_template_runs' => ['task_template_id' => 'task_templates'],
        'user_settings' => ['user_id' => 'users'],
        'workspaces' => ['owner_id' => 'users'],
        'roles' => ['workspace_id' => 'workspaces', 'created_by' => 'users'],
        'workspace_members' => ['workspace_id' => 'workspaces', 'user_id' => 'users', 'role_id' => 'roles'],
        'integration_connections' => ['workspace_id' => 'workspaces', 'user_id' => 'users'],
        'projects' => ['workspace_id' => 'workspaces', 'parent_project_id' => 'projects', 'created_by' => 'users', 'github_connection_id' => 'integration_connections'],
        'project_members' => ['project_id' => 'projects', 'workspace_member_id' => 'workspace_members', 'role_id' => 'roles'],
        'tasks' => ['workspace_id' => 'workspaces', 'project_id' => 'projects', 'parent_task_id' => 'tasks', 'assignee_id' => 'users', 'reviewer_id' => 'users', 'created_by' => 'users'],
        'task_dependencies' => ['predecessor_task_id' => 'tasks', 'successor_task_id' => 'tasks'],
        'task_blockers' => ['task_id' => 'tasks', 'reported_by' => 'users', 'resolved_by' => 'users'],
        'ai_runs' => ['workspace_id' => 'workspaces', 'project_id' => 'projects', 'requested_by' => 'users'],
        'ai_calls' => ['ai_run_id' => 'ai_runs'],
        'plans' => ['parent_plan_id' => 'plans', 'workspace_id' => 'workspaces', 'project_id' => 'projects', 'ai_run_id' => 'ai_runs', 'created_by' => 'users', 'reviewed_by' => 'users'],
        'calendar_events' => ['user_id' => 'users', 'workspace_id' => 'workspaces', 'project_id' => 'projects', 'task_id' => 'tasks', 'plan_id' => 'plans', 'integration_connection_id' => 'integration_connections'],
        'time_entries' => ['task_id' => 'tasks', 'user_id' => 'users', 'calendar_event_id' => 'calendar_events'],
        'task_activities' => ['task_id' => 'tasks', 'actor_id' => 'users', 'plan_id' => 'plans'],
        'notifications' => ['workspace_id' => 'workspaces', 'recipient_id' => 'users', 'task_id' => 'tasks', 'project_id' => 'projects'],
        'report_snapshots' => ['workspace_id' => 'workspaces', 'project_id' => 'projects', 'subject_user_id' => 'users', 'generated_by' => 'users', 'ai_run_id' => 'ai_runs'],
        'github_logs' => ['project_id' => 'projects', 'task_id' => 'tasks', 'integration_connection_id' => 'integration_connections'],
    ],
];
