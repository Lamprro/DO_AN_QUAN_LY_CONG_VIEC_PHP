<?php

namespace App\Policies;

use App\Authorization\PlanAccess;
use App\Authorization\ReportAccess;
use App\Models\AiRun;
use App\Models\User;

class AiRunPolicy
{
    public function __construct(private ReportAccess $reports, private PlanAccess $plans) {}

    public function view(User $actor, AiRun $run): bool
    {
        return $run->requested_by === $actor->id && ($run->operation->value === 'report' ? $this->reports->manifest($actor, $run->workspace, $run->context_snapshot['metrics'] ?? []) : $this->plans->run($actor, $run));
    }

    public function manage(User $actor, AiRun $run): bool
    {
        return $this->view($actor, $run) && ($run->operation->value === 'report' ? $this->reports->manifest($actor, $run->workspace, $run->context_snapshot['metrics'] ?? [], 'report.create') : $this->plans->run($actor, $run, 'plan.create'));
    }
}
