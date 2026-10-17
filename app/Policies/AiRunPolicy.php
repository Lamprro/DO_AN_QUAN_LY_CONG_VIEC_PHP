<?php

namespace App\Policies;

use App\Models\AiRun;
use App\Models\User;
use App\Services\PlanningContext;
use App\Support\ReportAccess;

class AiRunPolicy
{
    public function __construct(private ReportAccess $access) {}

    public function view(User $actor, AiRun $run): bool
    {
        return $run->requested_by === $actor->id && ($run->operation->value === 'report' ? $this->access->manifest($actor, $run->workspace, $run->context_snapshot['metrics'] ?? []) : app(PlanningContext::class)->allowed($actor, $run));
    }

    public function manage(User $actor, AiRun $run): bool
    {
        return $this->view($actor, $run) && ($run->operation->value === 'report' ? $this->access->manifest($actor, $run->workspace, $run->context_snapshot['metrics'] ?? [], 'report.create') : app(PlanningContext::class)->allowed($actor, $run, 'plan.create'));
    }
}
