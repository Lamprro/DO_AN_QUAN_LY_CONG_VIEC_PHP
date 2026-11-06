<?php

namespace App\View\Composers;

use App\Authorization\CalendarAccess;
use App\Authorization\PlanAccess;
use App\Authorization\ProjectAccess;
use App\Authorization\ReportAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Which module links the workspace/project overview pages may show. */
class ScopeLinksComposer
{
    public function __construct(
        private Request $request,
        private ReportAccess $reports,
        private CalendarAccess $calendars,
        private PlanAccess $plans,
        private ProjectAccess $projects,
    ) {}

    public function compose(View $view): void
    {
        $user = $this->request->user();
        $data = $view->getData();
        $workspace = $data['workspace'];
        $project = $data['project'] ?? null;
        $view->with([
            'canViewReport' => $user && $this->reports->scope($user, $workspace, $project),
            'canViewCalendar' => $user && $this->calendars->scope($user, $workspace, $project, 'calendar.view'),
            'canViewPlans' => $user && $this->plans->allows($user, $workspace, $project),
            'canViewIntegration' => $user && $project && $this->projects->allows($user, $project, 'integration.view'),
        ]);
    }
}
