<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarViewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->user = User::where('email', 'demo1@example.test')->firstOrFail();
        $this->workspace = Workspace::where('kind', 'team')->firstOrFail();
    }

    public function test_calendar_defaults_to_week_view(): void
    {
        $url = route('workspaces.calendar.index', $this->workspace);
        $response = $this->actingAs($this->user)->get($url);

        $response->assertOk();
        $range = $response->viewData('range');
        $this->assertEquals('week', $range['mode']);
        $this->assertCount(7, $range['days']);

        $response->assertSee('📅 Theo Tuần', false);
        $response->assertSee('🗓️ Theo Tháng', false);
        $response->assertSee('☀️ Theo Ngày', false);
        $response->assertSee('Thứ 2', false);
        $response->assertSee('Chủ nhật', false);
    }

    public function test_calendar_supports_month_view(): void
    {
        $url = route('workspaces.calendar.index', [$this->workspace, 'view' => 'month']);
        $response = $this->actingAs($this->user)->get($url);

        $response->assertOk();
        $range = $response->viewData('range');
        $this->assertEquals('month', $range['mode']);
        $this->assertGreaterThanOrEqual(28, count($range['days']));
    }

    public function test_calendar_supports_custom_date_range(): void
    {
        $url = route('workspaces.calendar.index', [
            $this->workspace,
            'from' => '2026-11-01',
            'to' => '2026-11-15',
        ]);
        $response = $this->actingAs($this->user)->get($url);

        $response->assertOk();
        $range = $response->viewData('range');
        $this->assertCount(14, $range['days']);
        $response->assertSee('Khoảng ngày: 01/11/2026 → 15/11/2026', false);
    }

    public function test_calendar_rejects_inverted_range(): void
    {
        $url = route('workspaces.calendar.index', [
            $this->workspace,
            'from' => '2026-11-15',
            'to' => '2026-11-01',
        ]);
        $response = $this->actingAs($this->user)->get($url);

        $response->assertSessionHasErrors('to');
    }
}
