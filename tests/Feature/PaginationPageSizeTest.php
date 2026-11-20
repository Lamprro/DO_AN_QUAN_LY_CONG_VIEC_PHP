<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginationPageSizeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $workspace;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->user = User::where('email', 'demo1@example.test')->firstOrFail();
        $this->workspace = Workspace::where('kind', 'team')->firstOrFail();
        $this->task = Task::where('workspace_id', $this->workspace->id)->whereNotNull('project_id')->firstOrFail();
    }

    public function test_tasks_list_supports_per_page_10_50_100_500(): void
    {
        $baseUrl = route('workspaces.projects.tasks.index', [$this->task->workspace_id, $this->task->project_id]);

        // Default is 10
        $response = $this->actingAs($this->user)->get($baseUrl);
        $response->assertOk();
        $this->assertEquals(10, $response->viewData('tasks')->perPage());

        // per_page = 50
        $response50 = $this->actingAs($this->user)->get($baseUrl.'?per_page=50');
        $response50->assertOk();
        $this->assertEquals(50, $response50->viewData('tasks')->perPage());

        // per_page = 100
        $response100 = $this->actingAs($this->user)->get($baseUrl.'?per_page=100');
        $response100->assertOk();
        $this->assertEquals(100, $response100->viewData('tasks')->perPage());

        // per_page = 500
        $response500 = $this->actingAs($this->user)->get($baseUrl.'?per_page=500');
        $response500->assertOk();
        $this->assertEquals(500, $response500->viewData('tasks')->perPage());

        // Invalid per_page should fail validation
        $responseInvalid = $this->actingAs($this->user)->get($baseUrl.'?per_page=999');
        $responseInvalid->assertSessionHasErrors('per_page');
    }

    public function test_projects_list_supports_per_page(): void
    {
        $url = route('workspaces.projects.index', $this->workspace);

        $response = $this->actingAs($this->user)->get($url);
        $response->assertOk();
        $this->assertEquals(10, $response->viewData('projects')->perPage());

        $response50 = $this->actingAs($this->user)->get($url.'?per_page=50');
        $response50->assertOk();
        $this->assertEquals(50, $response50->viewData('projects')->perPage());
    }

    public function test_workspaces_list_supports_per_page(): void
    {
        $url = route('workspaces.index');

        $response = $this->actingAs($this->user)->get($url);
        $response->assertOk();
        $this->assertEquals(10, $response->viewData('workspaces')->perPage());

        $response50 = $this->actingAs($this->user)->get($url.'?per_page=50');
        $response50->assertOk();
        $this->assertEquals(50, $response50->viewData('workspaces')->perPage());
    }

    public function test_pagination_native_renders_page_size_selector(): void
    {
        $url = route('workspaces.projects.tasks.index', [$this->task->workspace_id, $this->task->project_id]);
        $response = $this->actingAs($this->user)->get($url);

        $response->assertOk();
        $response->assertSee('Bản ghi / trang:', false);
        $response->assertSee('value="10"', false);
        $response->assertSee('value="50"', false);
        $response->assertSee('value="100"', false);
        $response->assertSee('value="500"', false);
    }
}
