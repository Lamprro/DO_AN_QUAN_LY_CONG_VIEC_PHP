<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskListFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->user = User::where('email', 'demo1@example.test')->firstOrFail();
        $this->task = Task::whereNotNull('project_id')->whereNull('archived_at')->firstOrFail();
    }

    private function listUrl(array $query = []): string
    {
        return route('workspaces.projects.tasks.index', [$this->task->workspace_id, $this->task->project_id, ...$query]);
    }

    public function test_list_renders_live_filter_saved_filters_and_bulk_bar(): void
    {
        $this->actingAs($this->user)->get($this->listUrl())
            ->assertOk()
            ->assertSee('data-live-filter', false)
            ->assertSee('js/task-filters.js', false)
            ->assertSee('data-saved-filters', false)
            ->assertSee('data-bulk-bar', false)
            ->assertSee('id="bulk-select-page"', false)
            ->assertDontSee('Bộ lọc đã lưu và cập nhật hàng loạt')
            ->assertDontSee('Áp dụng bộ lọc');
    }

    public function test_search_query_filters_the_list(): void
    {
        $this->actingAs($this->user)->get($this->listUrl(['q' => $this->task->code]))
            ->assertOk()
            ->assertSee($this->task->title);

        $this->actingAs($this->user)->get($this->listUrl(['q' => 'khong-co-viec-nao-zzz']))
            ->assertOk()
            ->assertSee('Chưa có công việc phù hợp');
    }

    public function test_project_list_rows_have_checkboxes_and_select_all(): void
    {
        $this->actingAs($this->user)->get($this->listUrl())
            ->assertOk()
            ->assertSee('form="bulk-tasks" name="items['.$this->task->id.']"', false)
            ->assertSee('id="bulk-select-page"', false);
    }

    public function test_personal_overview_can_select_and_bulk_update_assigned_project_tasks(): void
    {
        $personal = Workspace::where('kind', 'personal')->where('owner_id', $this->user->id)->firstOrFail();
        $assigned = Task::whereNotNull('project_id')->where('task_type', '!=', 'GROUP')->whereNull('archived_at')->firstOrFail();
        $assigned->forceFill(['assignee_id' => $this->user->id])->save();
        $assigned->refresh();

        $this->actingAs($this->user)->get(route('workspaces.tasks.index', $personal))
            ->assertOk()
            ->assertSee('form="bulk-tasks" name="items['.$assigned->id.']"', false);

        $target = $assigned->priority->value === 'urgent' ? 'low' : 'urgent';
        $this->actingAs($this->user)
            ->post(route('workspaces.tasks.bulk', $personal), [
                'action' => 'priority',
                'priority' => $target,
                'items' => [$assigned->id => $assigned->revision],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($target, $assigned->fresh()->priority->value);
    }

    public function test_bulk_rejects_tasks_outside_the_list(): void
    {
        $personal = Workspace::where('kind', 'personal')->where('owner_id', $this->user->id)->firstOrFail();
        // A project task not assigned to the user is not in the personal overview.
        $other = Task::whereNotNull('project_id')->where(fn ($q) => $q->whereNull('assignee_id')->orWhere('assignee_id', '!=', $this->user->id))->firstOrFail();
        $before = $other->priority->value;

        $this->actingAs($this->user)
            ->post(route('workspaces.tasks.bulk', $personal), [
                'action' => 'priority',
                'priority' => $before === 'urgent' ? 'low' : 'urgent',
                'items' => [$other->id => $other->revision],
            ])
            ->assertNotFound();

        $this->assertSame($before, $other->fresh()->priority->value);
    }

    public function test_board_view_keeps_live_filter(): void
    {
        $this->actingAs($this->user)->get($this->listUrl(['view' => 'board']))
            ->assertOk()
            ->assertSee('data-jira-board', false)
            ->assertSee('data-live-filter', false)
            ->assertSee('js/task-filters.js', false);
    }
}
