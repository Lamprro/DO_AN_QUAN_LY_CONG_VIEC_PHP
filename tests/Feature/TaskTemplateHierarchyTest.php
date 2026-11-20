<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Task\TaskTemplateService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskTemplateHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;
    private Project $project;
    private TaskTemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->user = User::where('email', 'demo1@example.test')->firstOrFail();
        $this->workspace = Workspace::where('name', 'Demo Team A')->firstOrFail();
        $this->project = Project::where('workspace_id', $this->workspace->id)->firstOrFail();
        $this->service = app(TaskTemplateService::class);
    }

    public function test_group_template_auto_includes_descendant_tasks_and_subtasks(): void
    {
        // Tạo 3 cấp: Group -> Task -> Subtask
        $group = Task::create([
            'workspace_id' => $this->workspace->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'title' => 'Nhóm Lặp Chu Kỳ Kế Toán',
            'code' => 'ACC-GRP',
            'task_type' => TaskType::GROUP,
            'status' => TaskStatus::TODO,
            'priority' => TaskPriority::HIGH,
            'parent_task_id' => null,
            'revision' => 1,
        ]);

        $task = Task::create([
            'workspace_id' => $this->workspace->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'title' => 'Lập Báo Cáo Tài Chính Tháng',
            'code' => 'ACC-1',
            'task_type' => TaskType::WORK,
            'status' => TaskStatus::TODO,
            'priority' => TaskPriority::HIGH,
            'parent_task_id' => $group->id,
            'revision' => 1,
        ]);

        $subtask = Task::create([
            'workspace_id' => $this->workspace->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'title' => 'Đối chiếu sổ phụ ngân hàng',
            'code' => 'ACC-2',
            'task_type' => TaskType::WORK,
            'status' => TaskStatus::TODO,
            'priority' => TaskPriority::MEDIUM,
            'parent_task_id' => $task->id,
            'revision' => 1,
        ]);

        // Người dùng CHỈ chọn đúng Group ID
        $template = $this->service->create($this->user, $this->workspace, $this->project, [
            'name' => 'Mẫu Kế Toán Hàng Tháng',
            'interval' => 1,
            'timezone' => 'Asia/Ho_Chi_Minh',
            'task_ids' => [$group->id],
        ]);

        // Kiểm tra template definition tự động mở rộng bao gồm cả 3 tasks
        $defTasks = collect($template->definition['tasks']);
        $this->assertCount(3, $defTasks);

        $defGroup = $defTasks->firstWhere('ref', $group->id);
        $defTask = $defTasks->firstWhere('ref', $task->id);
        $defSubtask = $defTasks->firstWhere('ref', $subtask->id);

        $this->assertNotNull($defGroup);
        $this->assertNotNull($defTask);
        $this->assertNotNull($defSubtask);

        $this->assertNull($defGroup['parent_ref']);
        $this->assertEquals($group->id, $defTask['parent_ref']);
        $this->assertEquals($task->id, $defSubtask['parent_ref']);

        // Instatiate template -> tạo lại đúng 3 cấp với ID mới
        $run = $this->service->instantiate($this->user, $template, 'manual:'.Str::uuid(), 1);
        $this->assertNotNull($run);

        $newGroupId = $run->task_map[$group->id];
        $newTaskId = $run->task_map[$task->id];
        $newSubtaskId = $run->task_map[$subtask->id];

        $newTask = Task::findOrFail($newTaskId);
        $newSubtask = Task::findOrFail($newSubtaskId);

        $this->assertEquals($newGroupId, $newTask->parent_task_id);
        $this->assertEquals($newTaskId, $newSubtask->parent_task_id);
    }

    public function test_standalone_task_template_without_parent_group_succeeds(): void
    {
        $group = Task::create([
            'workspace_id' => $this->workspace->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'title' => 'Group Cha Bị Bỏ Qua',
            'code' => 'IGN-GRP',
            'task_type' => TaskType::GROUP,
            'status' => TaskStatus::TODO,
            'priority' => TaskPriority::HIGH,
            'parent_task_id' => null,
            'revision' => 1,
        ]);

        $task = Task::create([
            'workspace_id' => $this->workspace->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'title' => 'Task Riêng Lẻ Được Chọn',
            'code' => 'SOLO-1',
            'task_type' => TaskType::WORK,
            'status' => TaskStatus::TODO,
            'priority' => TaskPriority::HIGH,
            'parent_task_id' => $group->id,
            'revision' => 1,
        ]);

        // Người dùng chọn RIÊNG task con, KHÔNG chọn group cha
        $template = $this->service->create($this->user, $this->workspace, $this->project, [
            'name' => 'Mẫu Task Độc Lập',
            'interval' => 1,
            'timezone' => 'Asia/Ho_Chi_Minh',
            'task_ids' => [$task->id],
        ]);

        // Hệ thống tự động chuyển parent_ref thành null để tạo độc lập, không ném lỗi
        $defTasks = collect($template->definition['tasks']);
        $this->assertCount(1, $defTasks);
        $this->assertNull($defTasks->first()['parent_ref']);

        // Instatiate -> tạo task mới độc lập có parent_task_id = null
        $run = $this->service->instantiate($this->user, $template, 'manual:'.Str::uuid(), 1);
        $newTaskId = $run->task_map[$task->id];
        $newTask = Task::findOrFail($newTaskId);
        $this->assertNull($newTask->parent_task_id);
    }

    public function test_standalone_subtask_template_without_parent_task_succeeds(): void
    {
        $task = Task::create([
            'workspace_id' => $this->workspace->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'title' => 'Task Cha Cấp 2 Bị Bỏ Qua',
            'code' => 'IGN-TSK',
            'task_type' => TaskType::WORK,
            'status' => TaskStatus::TODO,
            'priority' => TaskPriority::HIGH,
            'parent_task_id' => null,
            'revision' => 1,
        ]);

        $subtask = Task::create([
            'workspace_id' => $this->workspace->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'title' => 'Subtask Độc Lập Được Chọn',
            'code' => 'SOLO-SUB',
            'task_type' => TaskType::WORK,
            'status' => TaskStatus::TODO,
            'priority' => TaskPriority::MEDIUM,
            'parent_task_id' => $task->id,
            'revision' => 1,
        ]);

        // Người dùng chọn RIÊNG subtask, KHÔNG chọn task cha
        $template = $this->service->create($this->user, $this->workspace, $this->project, [
            'name' => 'Mẫu Subtask Độc Lập',
            'interval' => 1,
            'timezone' => 'Asia/Ho_Chi_Minh',
            'task_ids' => [$subtask->id],
        ]);

        $defTasks = collect($template->definition['tasks']);
        $this->assertCount(1, $defTasks);
        $this->assertNull($defTasks->first()['parent_ref']);

        $run = $this->service->instantiate($this->user, $template, 'manual:'.Str::uuid(), 1);
        $newSubtaskId = $run->task_map[$subtask->id];
        $newSubtask = Task::findOrFail($newSubtaskId);
        $this->assertNull($newSubtask->parent_task_id);
    }

    public function test_list_filters_by_group_and_tier(): void
    {
        $group = Task::create([
            'workspace_id' => $this->workspace->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'title' => 'Group Lọc Kiểm Thử',
            'code' => 'FLT-GRP',
            'task_type' => TaskType::GROUP,
            'status' => TaskStatus::TODO,
            'priority' => TaskPriority::HIGH,
            'parent_task_id' => null,
            'revision' => 1,
        ]);

        $task = Task::create([
            'workspace_id' => $this->workspace->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'title' => 'Task Lọc Cấp 2',
            'code' => 'FLT-TSK',
            'task_type' => TaskType::WORK,
            'status' => TaskStatus::TODO,
            'priority' => TaskPriority::HIGH,
            'parent_task_id' => $group->id,
            'revision' => 1,
        ]);

        $subtask = Task::create([
            'workspace_id' => $this->workspace->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'title' => 'Subtask Lọc Cấp 3',
            'code' => 'FLT-SUB',
            'task_type' => TaskType::WORK,
            'status' => TaskStatus::TODO,
            'priority' => TaskPriority::MEDIUM,
            'parent_task_id' => $task->id,
            'revision' => 1,
        ]);

        // 1. Lọc theo group_id
        $respGroup = $this->actingAs($this->user)->get(route('workspaces.projects.tasks.index', [
            $this->workspace->id,
            $this->project->id,
            'group_id' => $group->id,
            'layout' => 'flat',
        ]));
        $respGroup->assertOk()
            ->assertSee('Group Lọc Kiểm Thử')
            ->assertSee('Task Lọc Cấp 2')
            ->assertSee('Subtask Lọc Cấp 3');

        // 2. Lọc theo tier=tasks (chỉ Task cấp 2)
        $respTasks = $this->actingAs($this->user)->get(route('workspaces.projects.tasks.index', [
            $this->workspace->id,
            $this->project->id,
            'tier' => 'tasks',
            'per_page' => 100,
            'layout' => 'flat',
        ]));
        $respTasks->assertOk()
            ->assertSee('data-task-id="'.$task->id.'"', false)
            ->assertDontSee('data-task-id="'.$group->id.'"', false)
            ->assertDontSee('data-task-id="'.$subtask->id.'"', false);

        // 3. Lọc theo tier=subtasks (chỉ Sub-task cấp 3)
        $respSubtasks = $this->actingAs($this->user)->get(route('workspaces.projects.tasks.index', [
            $this->workspace->id,
            $this->project->id,
            'tier' => 'subtasks',
            'per_page' => 100,
            'layout' => 'flat',
        ]));
        $respSubtasks->assertOk()
            ->assertSee('data-task-id="'.$subtask->id.'"', false)
            ->assertDontSee('data-task-id="'.$group->id.'"', false)
            ->assertDontSee('data-task-id="'.$task->id.'"', false);
    }
}
