<?php

namespace Database\Seeders;

use App\Models\AiCall;
use App\Models\AiRun;
use App\Models\CalendarEvent;
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
use App\Models\User;
use App\Models\UserSetting;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/** Repeatable local fixtures. No external requests and no real integration credentials. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Không chạy dữ liệu demo trên production.');
        }

        DB::transaction(function (): void {
            $users = [];
            foreach (['Owner', 'Manager', 'Developer A', 'Developer B', 'Viewer'] as $index => $name) {
                $users[] = User::firstOrCreate(['email' => 'demo'.($index + 1).'@example.test'], [
                    'name' => $name, 'password' => Hash::make('Demo@123456'), 'status' => 'active',
                    'timezone' => 'Asia/Ho_Chi_Minh',
                ]);
                UserSetting::firstOrCreate(['user_id' => $users[$index]->id], ['settings' => config('work_management.settings_defaults')]);
            }

            $team = $this->workspace('Demo Team A', $users[0]);
            $other = $this->workspace('Demo Team B', $users[1]);
            $personal = $this->workspace('Demo Personal', $users[0], 'personal');
            $roles = $this->roles($team, $users[0]);
            $otherRoles = $this->roles($other, $users[1]);
            $personalRoles = $this->roles($personal, $users[0]);
            $members = [];
            foreach ($users as $i => $user) {
                $role = ['Owner', 'Manager', 'Member', 'Member', 'Viewer'][$i];
                $members[] = WorkspaceMember::firstOrCreate(['workspace_id' => $team->id, 'user_id' => $user->id], [
                    'role_id' => $roles[$role]->id, 'status' => 'active', 'joined_at' => '2026-10-01 00:00:00',
                ]);
            }
            $otherMember = WorkspaceMember::firstOrCreate(['workspace_id' => $other->id, 'user_id' => $users[1]->id], ['role_id' => $otherRoles['Owner']->id, 'joined_at' => '2026-10-01 00:00:00']);
            WorkspaceMember::firstOrCreate(['workspace_id' => $personal->id, 'user_id' => $users[0]->id], ['role_id' => $personalRoles['Owner']->id, 'joined_at' => '2026-10-01 00:00:00']);

            $project = Project::firstOrCreate(['workspace_id' => $team->id, 'name' => 'Demo Work Management'], ['created_by' => $users[0]->id, 'status' => 'active', 'start_date' => '2026-10-01', 'deadline' => '2026-12-15']);
            $child = Project::firstOrCreate(['workspace_id' => $team->id, 'name' => 'Demo Backend'], ['parent_project_id' => $project->id, 'created_by' => $users[0]->id, 'status' => 'active']);
            $otherProject = Project::firstOrCreate(['workspace_id' => $other->id, 'name' => 'Demo Isolated Project'], ['created_by' => $users[1]->id, 'status' => 'active']);
            foreach ($members as $i => $member) {
                ProjectMember::firstOrCreate(['project_id' => $project->id, 'workspace_member_id' => $member->id], ['role_id' => $member->role_id, 'joined_at' => '2026-10-01 00:00:00']);
                if (in_array($i, [0, 2], true)) {
                    ProjectMember::firstOrCreate(['project_id' => $child->id, 'workspace_member_id' => $member->id], ['role_id' => $member->role_id, 'joined_at' => '2026-10-01 00:00:00']);
                }
            }
            ProjectMember::firstOrCreate(['project_id' => $otherProject->id, 'workspace_member_id' => $otherMember->id], ['role_id' => $otherMember->role_id, 'joined_at' => '2026-10-01 00:00:00']);

            $group = Task::firstOrCreate(['workspace_id' => $team->id, 'code' => 'DEMO-GROUP'], ['project_id' => $project->id, 'title' => 'Hoàn thiện MVP', 'task_type' => 'GROUP', 'created_by' => $users[0]->id]);
            $tasks = [];
            $statuses = ['TODO', 'IN_PROGRESS', 'REVIEW', 'DONE'];
            for ($i = 1; $i <= 36; $i++) {
                $tasks[] = Task::firstOrCreate(['workspace_id' => $team->id, 'code' => 'DEMO-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)], [
                    'project_id' => $project->id, 'parent_task_id' => $group->id, 'task_type' => 'WORK',
                    'title' => 'Công việc mẫu '.$i, 'created_by' => $users[0]->id, 'assignee_id' => $users[$i % 4]->id,
                    'reviewer_id' => $users[1]->id, 'status' => $statuses[($i - 1) % 4], 'priority' => ['low', 'medium', 'high'][$i % 3],
                    'estimate_minutes' => 60 + $i * 5, 'sort_order' => $i, 'deadline_at' => '2026-12-01 10:00:00',
                    'completed_at' => $i % 4 === 0 ? '2026-10-10 10:00:00' : null,
                ]);
            }
            Task::firstOrCreate(['workspace_id' => $team->id, 'code' => 'DEMO-MILESTONE'], ['project_id' => $project->id, 'parent_task_id' => $group->id, 'title' => 'Sẵn sàng demo', 'task_type' => 'MILESTONE', 'created_by' => $users[0]->id]);
            Task::firstOrCreate(['workspace_id' => $other->id, 'code' => 'PRIVATE-001'], ['project_id' => $otherProject->id, 'title' => 'Không thuộc Team A', 'created_by' => $users[1]->id]);
            Task::firstOrCreate(['workspace_id' => $personal->id, 'code' => 'PERSONAL-001'], ['title' => 'Công việc cá nhân', 'created_by' => $users[0]->id]);
            TaskDependency::firstOrCreate(['predecessor_task_id' => $tasks[0]->id, 'successor_task_id' => $tasks[1]->id], ['dependency_type' => 'FS', 'lag_minutes' => 0]);
            TaskBlocker::firstOrCreate(['task_id' => $tasks[1]->id, 'reason' => 'Đang chờ chốt đặc tả demo'], ['reported_by' => $users[2]->id]);

            $run = AiRun::firstOrCreate(['idempotency_key' => 'demo-fixture-planning-v1'], [
                'workspace_id' => $team->id, 'project_id' => $project->id, 'requested_by' => $users[0]->id,
                'operation' => 'planning', 'trigger_type' => 'demo_fixture', 'user_prompt' => 'Dữ liệu mô phỏng cho frontend, không phải kết quả AI thật.',
                'context_snapshot' => ['fixture' => true, 'work_schedule' => config('work_schedule')], 'status' => 'succeeded',
            ]);
            AiCall::firstOrCreate(['ai_run_id' => $run->id, 'step_key' => 'fixture', 'attempt_no' => 1], [
                'provider' => 'fixture', 'model' => 'demo-only', 'request_payload' => ['fixture' => true],
                'response_payload' => ['fixture' => true], 'status' => 'succeeded', 'started_at' => '2026-10-15 00:00:00', 'finished_at' => '2026-10-15 00:00:01',
            ]);
            $plan = Plan::firstOrCreate(['workspace_id' => $team->id, 'name' => 'Demo proposal (fixture)', 'version_no' => 1], [
                'project_id' => $project->id, 'plan_group_id' => (string) Str::uuid(), 'ai_run_id' => $run->id,
                'data' => ['schema_version' => 1, 'fixture' => true, 'tasks' => [], 'events' => []],
                'status' => 'proposed', 'is_active' => false, 'created_by' => $users[0]->id,
            ]);

            for ($i = 0; $i < 20; $i++) {
                $task = $tasks[$i];
                $start = CarbonImmutable::parse('2026-10-19 01:00:00', 'UTC')->addDays($i);
                CalendarEvent::firstOrCreate(['user_id' => $task->assignee_id, 'task_id' => $task->id, 'title' => 'Demo slot '.($i + 1)], [
                    'workspace_id' => $team->id, 'project_id' => $project->id, 'event_type' => 'TASK_BLOCK',
                    'starts_at' => $start, 'ends_at' => $start->addHour(), 'is_busy' => true, 'visibility' => 'project', 'participants' => [],
                ]);
            }
            TaskActivity::firstOrCreate(['task_id' => $tasks[0]->id, 'type' => 'COMMENT', 'content' => 'Bình luận demo cho giao diện.'], ['actor_id' => $users[2]->id, 'source' => 'user']);
            TaskActivity::firstOrCreate(['task_id' => $tasks[0]->id, 'type' => 'HISTORY', 'event_type' => 'fixture_created'], ['actor_id' => $users[0]->id, 'source' => 'system', 'data' => ['fixture' => true]]);
            Notification::firstOrCreate(['dedupe_key' => 'demo:task-assigned:'.$users[2]->id], ['workspace_id' => $team->id, 'recipient_id' => $users[2]->id, 'task_id' => $tasks[0]->id, 'project_id' => $project->id, 'type' => 'task_assigned', 'title' => 'Thông báo mẫu', 'body' => 'Dữ liệu demo, chưa có chức năng gửi email.', 'deliveries' => []]);
            ReportSnapshot::firstOrCreate(['workspace_id' => $team->id, 'scope' => 'project', 'period_start' => '2026-10-01 00:00:00', 'period_end' => '2026-11-01 00:00:00'], [
                'project_id' => $project->id, 'generated_by' => $users[0]->id,
                'metrics_json' => ['fixture' => true, 'work_tasks' => 36, 'done_tasks' => 9, 'estimate_minutes' => 5490],
            ]);
        });
    }

    private function workspace(string $name, User $owner, string $kind = 'team'): Workspace
    {
        return Workspace::firstOrCreate(['name' => $name, 'owner_id' => $owner->id], ['kind' => $kind, 'timezone' => 'Asia/Ho_Chi_Minh']);
    }

    private function roles(Workspace $workspace, User $owner): array
    {
        $roles = [];
        foreach (config('permissions.defaults') as $name => $permissions) {
            $roles[$name] = Role::firstOrCreate(['workspace_id' => $workspace->id, 'name' => $name], ['permissions' => $permissions, 'is_system' => true, 'created_by' => $owner->id]);
        }

        return $roles;
    }
}
