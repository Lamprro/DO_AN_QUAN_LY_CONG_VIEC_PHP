<?php

namespace App\Services;

use App\Jobs\ProcessAiRun;
use App\Models\AiRun;
use App\Models\TaskReminder;
use App\Models\User;
use App\Models\Workspace;
use App\Support\ProjectAccess;
use App\Support\WorkspaceAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class QueueObservation
{
    public function data(User $actor, Workspace $workspace): array
    {
        abort_unless(app(WorkspaceAccess::class)->allows($actor, $workspace, 'workspace.view_operations'), 403);
        // Explicitly restricted to own runs and current source access, even for an operator.
        $runs = AiRun::where('workspace_id', $workspace->id)->where('requested_by', $actor->id)->withCount('calls')->latest()->limit(100)->get()->filter(fn ($r) => Gate::forUser($actor)->allows('view', $r));
        $ids = $runs->pluck('id')->all();
        $jobs = [];
        $jobsAvailable = ! config('queue.connections.database.connection') || config('queue.connections.database.connection') === config('database.default');
        $pending = $jobsAvailable ? DB::table('jobs')->where('queue', config('ai.queue'))->select('id', 'queue', 'attempts', 'reserved_at', 'available_at', 'payload')->orderBy('id')->limit(1001)->get() : collect();
        $limited = $pending->count() > 1000;
        foreach ($pending->take(1000) as $job) {
            $p = json_decode($job->payload, true);
            if (($p['data']['commandName'] ?? '') !== ProcessAiRun::class) {
                continue;
            }foreach ($ids as $id) {
                if (str_contains($p['data']['command'] ?? '', 's:5:"runId";s:36:"'.$id.'"')) {
                    $jobs[] = ['id' => $job->id, 'run_id' => $id, 'attempts' => $job->attempts, 'reserved' => $job->reserved_at !== null, 'available_at' => $job->available_at];
                    break;
                }
            }
        }
        $failedJobs = [];
        foreach (($jobsAvailable ? DB::table('failed_jobs')->where('queue', config('ai.queue'))->select('id', 'failed_at', 'payload')->latest('id')->limit(1000)->get() : collect()) as $job) {
            $p = json_decode($job->payload, true);
            if (($p['data']['commandName'] ?? '') !== ProcessAiRun::class) {
                continue;
            }foreach ($ids as $id) {
                if (str_contains($p['data']['command'] ?? '', 's:5:"runId";s:36:"'.$id.'"')) {
                    $failedJobs[] = ['id' => $job->id, 'run_id' => $id, 'failed_at' => $job->failed_at];
                    break;
                }
            }
        }
        $reminders = TaskReminder::where('workspace_id', $workspace->id)->where('user_id', $actor->id)->whereHas('task', fn ($q) => $q->whereIn('project_id', app(ProjectAccess::class)->visible($actor, $workspace)->get()->filter(fn ($p) => app(ProjectAccess::class)->allows($actor, $p, 'task.view'))->pluck('id'))->when($workspace->kind->value === 'personal', fn ($q) => $q->orWhereNull('project_id')))->latest()->limit(50)->get();

        return compact('runs', 'jobs', 'limited', 'reminders', 'failedJobs', 'jobsAvailable');
    }
}
