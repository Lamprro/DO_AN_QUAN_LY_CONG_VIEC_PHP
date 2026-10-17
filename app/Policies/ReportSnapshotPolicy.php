<?php

namespace App\Policies;

use App\Models\ReportSnapshot;
use App\Models\User;
use App\Support\ReportAccess;

class ReportSnapshotPolicy
{
    public function __construct(private ReportAccess $access) {}

    public function view(User $user, ReportSnapshot $snapshot): bool
    {
        return $snapshot->generated_by === $user->id && $this->access->manifest($user, $snapshot->workspace, $snapshot->metrics_json ?? []);
    }
}
