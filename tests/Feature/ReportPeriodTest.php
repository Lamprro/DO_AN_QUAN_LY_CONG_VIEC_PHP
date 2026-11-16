<?php

namespace Tests\Feature;

use App\Models\ReportSnapshot;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Support\UiDate;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPeriodTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $personal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->user = User::where('email', 'demo1@example.test')->firstOrFail();
        $this->personal = Workspace::where('kind', 'personal')->where('owner_id', $this->user->id)->firstOrFail();
    }

    private function expectedPersonalWork(): int
    {
        $own = Task::where('workspace_id', $this->personal->id)->whereNull('project_id')->where('task_type', 'WORK')->whereNull('archived_at')->count();
        $assigned = Task::whereNotNull('project_id')->where('assignee_id', $this->user->id)->where('task_type', 'WORK')->whereNull('archived_at')->count();

        return $own + $assigned;
    }

    public function test_personal_dashboard_counts_the_same_work_as_the_personal_task_list(): void
    {
        $expected = $this->expectedPersonalWork();
        $this->assertGreaterThan(1, $expected, 'Seeder should assign project work to demo1');

        $this->actingAs($this->user)
            ->get(route('workspaces.dashboard', [$this->personal, 'from' => '2025-12-05', 'to' => '2026-11-16']))
            ->assertOk()
            ->assertSeeText('Tổng WORK: '.$expected)
            ->assertSeeText('05/12/2025 → 16/11/2026')
            ->assertSee('Việc riêng và việc project được giao cho bạn');
    }

    public function test_personal_snapshot_with_project_tasks_can_be_opened_again(): void
    {
        $this->actingAs($this->user)
            ->post(route('workspaces.reports.store', $this->personal), ['from' => '2025-12-05', 'to' => '2026-11-16'])
            ->assertRedirect();

        $snapshot = ReportSnapshot::where('workspace_id', $this->personal->id)->latest('created_at')->firstOrFail();
        $this->assertSame($this->expectedPersonalWork(), $snapshot->metrics_json['inventory']['work_count']);
        $this->assertNotEmpty($snapshot->metrics_json['access']['project_ids']);

        $this->actingAs($this->user)
            ->get(route('workspaces.reports.show', [$this->personal, $snapshot]))
            ->assertOk();
    }

    public function test_team_dashboard_is_unchanged(): void
    {
        $team = Workspace::where('kind', 'team')->whereHas('members', fn ($q) => $q->where('user_id', $this->user->id))->firstOrFail();
        $this->actingAs($this->user)
            ->get(route('workspaces.dashboard', [$team, 'from' => '2025-12-05', 'to' => '2026-11-16']))
            ->assertOk()
            ->assertSee('Các project được phép xem');
    }

    public function test_ui_date_formats_day_month_year(): void
    {
        $this->assertSame('05/12/2025', UiDate::day('2025-12-05'));
        $this->assertSame('', UiDate::day(null));
        $this->assertSame('not-a-date', UiDate::day('not-a-date'));
    }
}
