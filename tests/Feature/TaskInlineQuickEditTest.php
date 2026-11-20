<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskInlineQuickEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_inline_quick_edit_triggers_are_rendered_in_task_list(): void
    {
        $user = User::where('email', 'demo1@example.test')->first();
        $task = Task::where('task_type', '!=', 'GROUP')->first();
        $this->assertNotNull($task);

        $workspace = $task->workspace;
        $url = $task->project_id
            ? "/workspaces/{$workspace->id}/projects/{$task->project_id}/tasks"
            : "/workspaces/{$workspace->id}/tasks";

        $response = $this->actingAs($user)->get($url);

        $response->assertStatus(200);
        $response->assertSee('inline-edit-trigger', false);
        $response->assertSee('inline-priority-trigger', false);
        $response->assertSee('inline-status-trigger', false);
        $response->assertSee('inline-assignee-trigger', false);
        $response->assertSee('inline-deadline-trigger', false);
        $response->assertSee('workspace-members-data', false);
        $response->assertSee('deadline-presets-data', false);
    }

    public function test_inline_quick_edit_priority_via_ajax(): void
    {
        $user = User::where('email', 'demo1@example.test')->first();
        $task = Task::where('task_type', '!=', 'GROUP')->first();
        $workspace = $task->workspace;

        $url = $task->project_id
            ? "/workspaces/{$workspace->id}/projects/{$task->project_id}/tasks/{$task->id}"
            : "/workspaces/{$workspace->id}/tasks/{$task->id}";

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->patchJson($url, [
                'edit_surface' => 'inline',
                'expected_revision' => $task->revision,
                'priority' => 'urgent',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'task' => [
                'id' => $task->id,
                'priority' => 'urgent',
            ],
        ]);

        $this->assertEquals('urgent', $task->fresh()->priority->value);
    }

    public function test_inline_quick_edit_assignee_via_ajax(): void
    {
        $user = User::where('email', 'demo1@example.test')->first();
        $task = Task::where('task_type', '!=', 'GROUP')->first();
        $workspace = $task->workspace;

        $url = $task->project_id
            ? "/workspaces/{$workspace->id}/projects/{$task->project_id}/tasks/{$task->id}"
            : "/workspaces/{$workspace->id}/tasks/{$task->id}";

        // Assign to user
        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->patchJson($url, [
                'edit_surface' => 'inline',
                'expected_revision' => $task->revision,
                'assignee_id' => $user->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'task' => [
                'id' => $task->id,
                'assignee_id' => $user->id,
            ],
        ]);
        $this->assertEquals($user->id, $task->fresh()->assignee_id);

        // Unassign
        $responseUnassign = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->patchJson($url, [
                'edit_surface' => 'inline',
                'expected_revision' => $task->fresh()->revision,
                'assignee_id' => null,
            ]);

        $responseUnassign->assertStatus(200);
        $this->assertNull($task->fresh()->assignee_id);
    }

    public function test_inline_quick_edit_deadline_via_ajax(): void
    {
        $user = User::where('email', 'demo1@example.test')->first();
        $task = Task::where('task_type', '!=', 'GROUP')->first();
        $workspace = $task->workspace;

        $url = $task->project_id
            ? "/workspaces/{$workspace->id}/projects/{$task->project_id}/tasks/{$task->id}"
            : "/workspaces/{$workspace->id}/tasks/{$task->id}";

        $newDeadline = now()->addDays(5)->format('Y-m-d\TH:i');

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->patchJson($url, [
                'edit_surface' => 'inline',
                'expected_revision' => $task->revision,
                'deadline_at' => $newDeadline,
                'deadline_reason' => 'Điều chỉnh kế hoạch sprint từ test inline',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'task' => [
                'id' => $task->id,
            ],
        ]);
        $this->assertNotNull($task->fresh()->deadline_at);
    }

    public function test_inline_quick_edit_status_via_ajax(): void
    {
        $user = User::where('email', 'demo1@example.test')->first();
        $task = Task::where('task_type', '!=', 'GROUP')->where('status', 'TODO')->first();
        $this->assertNotNull($task);

        $workspace = $task->workspace;
        $url = $task->project_id
            ? "/workspaces/{$workspace->id}/projects/{$task->project_id}/tasks/{$task->id}/status"
            : "/workspaces/{$workspace->id}/tasks/{$task->id}/status";

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->patchJson($url, [
                'expected_status' => 'TODO',
                'status' => 'IN_PROGRESS',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'IN_PROGRESS',
        ]);
        $this->assertEquals('IN_PROGRESS', $task->fresh()->status->value);
    }

    public function test_inline_quick_edit_triggers_rendered_in_project_overview(): void
    {
        $user = User::where('email', 'demo1@example.test')->first();
        $task = Task::whereNotNull('project_id')->first();
        $this->assertNotNull($task);

        $workspace = $task->workspace;
        $url = "/workspaces/{$workspace->id}/projects/{$task->project_id}";

        $response = $this->actingAs($user)->get($url);

        $response->assertStatus(200);
        $response->assertSee('inline-edit-trigger', false);
        $response->assertSee('workspace-members-data', false);
        $response->assertSee('deadline-presets-data', false);
    }
}
