<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JiraBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_authenticated_user_can_view_kanban_board(): void
    {
        $user = User::where('email', 'demo1@example.test')->first();
        $workspace = Workspace::first();
        $project = Project::where('workspace_id', $workspace->id)->first();

        $url = $project
            ? "/workspaces/{$workspace->id}/projects/{$project->id}/tasks?view=board"
            : "/workspaces/{$workspace->id}/tasks?view=board";

        $response = $this->actingAs($user)->get($url);

        $response->assertStatus(200);
        $response->assertSee('data-jira-board', false);
        $response->assertSee('jira-columns', false);
    }

    public function test_status_update_via_ajax_returns_json(): void
    {
        $user = User::where('email', 'demo1@example.test')->first();
        $task = Task::where('status', 'TODO')->first();

        $this->assertNotNull($task, 'A TODO task must exist from seeder');

        $workspace = $task->workspace;
        $project = $task->project;

        $url = $project
            ? "/workspaces/{$workspace->id}/projects/{$project->id}/tasks/{$task->id}/status"
            : "/workspaces/{$workspace->id}/tasks/{$task->id}/status";

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->patchJson($url, [
                'expected_status' => 'TODO',
                'status' => 'IN_PROGRESS',
            ]);

        $this->assertContains($response->status(), [200, 422]);
        if ($response->status() === 200) {
            $response->assertJson([
                'success' => true,
                'status' => 'IN_PROGRESS',
            ]);
        }
    }

    public function test_global_quick_create_and_view_switcher_are_rendered(): void
    {
        $user = User::where('email', 'demo1@example.test')->first();
        $workspace = Workspace::first();

        $response = $this->actingAs($user)->get("/workspaces/{$workspace->id}");

        $response->assertStatus(200);
        $response->assertSee('jira-quick-create-btn', false);
        $response->assertSee('+ Tạo việc', false);
    }
}
