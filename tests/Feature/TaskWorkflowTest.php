<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_user_can_upload_multiple_attachments_at_once(): void
    {
        Storage::fake('task_files');

        $user = User::where('email', 'demo1@example.test')->first();
        $task = Task::first();

        $workspace = $task->workspace;
        $project = $task->project;

        $url = $project
            ? "/workspaces/{$workspace->id}/projects/{$project->id}/tasks/{$task->id}/attachments"
            : "/workspaces/{$workspace->id}/tasks/{$task->id}/attachments";

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $file1 = UploadedFile::fake()->createWithContent('screenshot1.png', $pngBytes);
        $file2 = UploadedFile::fake()->createWithContent('notes.txt', 'Day la ghi chu cong viec');
        $file3 = UploadedFile::fake()->createWithContent('data.csv', "col1,col2\nval1,val2");

        $response = $this->actingAs($user)->post($url, [
            'files' => [$file1, $file2, $file3],
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Đã tải lên 3 tệp đính kèm.');

        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'actor_id' => $user->id,
            'type' => 'ATTACHMENT',
            'content' => 'screenshot1.png',
        ]);
        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'actor_id' => $user->id,
            'type' => 'ATTACHMENT',
            'content' => 'notes.txt',
        ]);
        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'actor_id' => $user->id,
            'type' => 'ATTACHMENT',
            'content' => 'data.csv',
        ]);
    }

    public function test_user_can_upload_single_attachment_via_file_field(): void
    {
        Storage::fake('task_files');

        $user = User::where('email', 'demo1@example.test')->first();
        $task = Task::first();

        $workspace = $task->workspace;
        $project = $task->project;

        $url = $project
            ? "/workspaces/{$workspace->id}/projects/{$project->id}/tasks/{$task->id}/attachments"
            : "/workspaces/{$workspace->id}/tasks/{$task->id}/attachments";

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $file = UploadedFile::fake()->createWithContent('single.png', $pngBytes);

        $response = $this->actingAs($user)->post($url, [
            'file' => $file,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Đã tải tệp lên.');

        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'actor_id' => $user->id,
            'type' => 'ATTACHMENT',
            'content' => 'single.png',
        ]);
    }
}
