<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskHierarchyJiraTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->user = User::where('email', 'demo1@example.test')->firstOrFail();
        $this->workspace = Workspace::where('name', 'Demo Team A')->firstOrFail();
        $this->project = Project::where('workspace_id', $this->workspace->id)->firstOrFail();
    }

    public function test_three_tier_hierarchy_and_epic_grouping(): void
    {
        // 1. Level 1: Epic / Nhóm cha
        $group = Task::create([
            'workspace_id' => $this->workspace->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'title' => 'Epic Tính Năng Thanh Toán 2026',
            'code' => 'PAY-EPIC',
            'task_type' => TaskType::GROUP,
            'status' => TaskStatus::IN_PROGRESS,
            'priority' => TaskPriority::HIGH,
            'parent_task_id' => null,
            'revision' => 1,
        ]);

        // 2. Level 2: Task chuẩn thuộc Group
        $task = Task::create([
            'workspace_id' => $this->workspace->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'title' => 'Tích hợp cổng VNPay',
            'code' => 'PAY-1',
            'task_type' => TaskType::WORK,
            'status' => TaskStatus::IN_PROGRESS,
            'priority' => TaskPriority::HIGH,
            'parent_task_id' => $group->id,
            'revision' => 1,
        ]);

        // 3. Level 3: Sub-task con của Task chuẩn
        $subtask = Task::create([
            'workspace_id' => $this->workspace->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'title' => 'Xác thực chữ ký checksum IPN',
            'code' => 'PAY-2',
            'task_type' => TaskType::WORK,
            'status' => TaskStatus::TODO,
            'priority' => TaskPriority::MEDIUM,
            'parent_task_id' => $task->id,
            'revision' => 1,
        ]);

        // Kiểm tra quan hệ cấp bậc
        $this->assertEquals($group->id, $task->parent_task_id);
        $this->assertEquals($task->id, $subtask->parent_task_id);
        $this->assertEquals($group->id, $subtask->parent->parent_task_id);

        // Kiểm tra hiển thị chi tiết Task Level 2 (show) có breadcrumb và danh sách subtask
        $response = $this->actingAs($this->user)->get(route('workspaces.projects.tasks.show', [
            $this->workspace->id,
            $this->project->id,
            $task->id,
        ]));

        $response->assertOk()
            ->assertSee('Epic Tính Năng Thanh Toán 2026')
            ->assertSee('Xác thực chữ ký checksum IPN')
            ->assertSee('PAY-2')
            ->assertSee('+ Thêm việc con (Sub-task)');

        // Kiểm tra trang danh sách tasks/index hiển thị cụm Epic và Subtask
        $listResponse = $this->actingAs($this->user)->get(route('workspaces.projects.tasks.index', [
            $this->workspace->id,
            $this->project->id,
        ]));

        $listResponse->assertOk()
            ->assertSee('NHÓM CHA (EPIC)')
            ->assertSee('PAY-EPIC')
            ->assertSee('PAY-1')
            ->assertSee('PAY-2')
            ->assertSee('SUB-TASK (CẤP 3)');

        // Kiểm tra Kanban Board có Epic filter bar và card epic pill
        $boardResponse = $this->actingAs($this->user)->get(route('workspaces.projects.tasks.index', [
            $this->workspace->id,
            $this->project->id,
            'view' => 'board',
        ]));

        $boardResponse->assertOk()
            ->assertSee('data-epic-bar', false)
            ->assertSee('PAY-EPIC')
            ->assertSee('PAY-1');
    }

    public function test_slide_panel_preselects_parent_task(): void
    {
        $group = Task::where('project_id', $this->project->id)->where('task_type', TaskType::GROUP)->firstOrFail();

        $panelResponse = $this->actingAs($this->user)->get(route('workspaces.projects.tasks.create', [
            $this->workspace->id,
            $this->project->id,
            'parent_task_id' => $group->id,
        ]), ['X-Requested-With' => 'XMLHttpRequest']);

        $panelResponse->assertOk()
            ->assertSee('Đang tạo công việc con cho:')
            ->assertSee($group->code)
            ->assertSee('value="'.$group->id.'"', false);
    }
}
