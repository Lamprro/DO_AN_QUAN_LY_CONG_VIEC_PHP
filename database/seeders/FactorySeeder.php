<?php

namespace Database\Seeders;

use App\Models\CalendarEvent;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FactorySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $users = User::factory()->count(3)->create([
                'password' => bcrypt('Demo@123456'),
            ]);

            $owner = $users[0];

            $workspace = Workspace::factory()->create([
                'name' => 'Demo Tech Workspace',
                'owner_id' => $owner->id,
            ]);

            $roles = [];
            foreach (config('permissions.defaults') as $name => $permissions) {
                $roles[$name] = Role::firstOrCreate(
                    ['workspace_id' => $workspace->id, 'name' => $name],
                    ['permissions' => $permissions, 'is_system' => true, 'created_by' => $owner->id]
                );
            }

            $members = [];
            foreach ($users as $i => $u) {
                $roleName = ['Owner', 'Manager', 'Member'][$i];
                $members[] = WorkspaceMember::factory()->create([
                    'workspace_id' => $workspace->id,
                    'user_id' => $u->id,
                    'role_id' => $roles[$roleName]->id,
                ]);
            }

            $project = Project::factory()->create([
                'workspace_id' => $workspace->id,
                'name' => 'Dự án Phát triển Web',
                'created_by' => $owner->id,
            ]);

            foreach ($members as $m) {
                ProjectMember::factory()->create([
                    'project_id' => $project->id,
                    'workspace_member_id' => $m->id,
                    'role_id' => $m->role_id,
                ]);
            }

            $tasks = Task::factory()->count(10)->create([
                'workspace_id' => $workspace->id,
                'project_id' => $project->id,
                'created_by' => $owner->id,
                'assignee_id' => $users[1]->id,
            ]);

            TaskActivity::factory()->create([
                'task_id' => $tasks[0]->id,
                'actor_id' => $users[1]->id,
                'content' => 'Cập nhật tiến độ dự án.',
            ]);

            CalendarEvent::factory()->create([
                'workspace_id' => $workspace->id,
                'project_id' => $project->id,
                'user_id' => $users[1]->id,
                'title' => 'Họp kế hoạch tuần',
            ]);

            Notification::factory()->create([
                'workspace_id' => $workspace->id,
                'recipient_id' => $users[1]->id,
                'project_id' => $project->id,
                'task_id' => $tasks[0]->id,
                'title' => 'Bạn có công việc mới được phân công',
            ]);
        });
    }
}
