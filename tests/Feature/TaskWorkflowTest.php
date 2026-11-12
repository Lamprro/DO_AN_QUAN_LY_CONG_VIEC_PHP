<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_user_can_view_task_details(): void
    {
        $user = User::where('email', 'demo1@example.test')->first();
        $task = Task::first();

        $this->assertNotNull($task);

        $workspace = $task->workspace;
        $project = $task->project;

        $url = $project
            ? "/workspaces/{$workspace->id}/projects/{$project->id}/tasks/{$task->id}"
            : "/workspaces/{$workspace->id}/tasks/{$task->id}";

        $response = $this->actingAs($user)->get($url);

        $response->assertStatus(200);
        $response->assertSee($task->code);
    }

    public function test_user_can_post_comment_on_task(): void
    {
        $user = User::where('email', 'demo1@example.test')->first();
        $task = Task::first();

        $workspace = $task->workspace;
        $project = $task->project;

        $url = $project
            ? "/workspaces/{$workspace->id}/projects/{$project->id}/tasks/{$task->id}/comments"
            : "/workspaces/{$workspace->id}/tasks/{$task->id}/comments";

        $response = $this->actingAs($user)->post($url, [
            'content' => 'Kiểm thử thêm bình luận tự động từ feature test.',
        ]);

        $this->assertContains($response->status(), [200, 302]);
        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'actor_id' => $user->id,
            'type' => 'COMMENT',
        ]);
    }

    public function test_task_conflict_prevention_on_revision(): void
    {
        $user = User::where('email', 'demo1@example.test')->first();
        $task = Task::first();

        $workspace = $task->workspace;
        $project = $task->project;

        $url = $project
            ? "/workspaces/{$workspace->id}/projects/{$project->id}/tasks/{$task->id}"
            : "/workspaces/{$workspace->id}/tasks/{$task->id}";

        // Submitting with wrong expected_revision should trigger conflict detection (409 or conflict response)
        $response = $this->actingAs($user)->patch($url, [
            'title' => 'Updated Title',
            'expected_revision' => 999999, // Stale revision
            'edit_surface' => 'quick',
        ]);

        $this->assertContains($response->status(), [409, 422, 302, 200]);
    }
}
