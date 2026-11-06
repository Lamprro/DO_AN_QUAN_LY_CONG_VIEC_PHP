<?php

namespace App\Policies;

use App\Authorization\ReportAccess;
use App\Models\ReportSnapshot;
use App\Models\User;

class ReportSnapshotPolicy
{
    public function __construct(private ReportAccess $access) {}

    public function view(User $user, ReportSnapshot $snapshot): bool
    {
        return $snapshot->generated_by === $user->id && $this->access->manifest($user, $snapshot->workspace, $snapshot->metrics_json ?? []);
    }
}
