<?php

namespace App\Services;

use App\Models\GithubLog;
use App\Models\Project;
use App\Models\TaskActivity;
use App\Models\Workspace;
use App\Support\ProjectAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GithubWebhookService
{
    public function receive(Request $request, Project $project): void
    {
        abort_unless(config('work_management.integrations_enabled'), 503);
        $raw = $request->getContent();
        abort_if(strlen($raw) > 262144, 413);
        $connection = $project->githubConnection;
        abort_unless($connection && $connection->status->value === 'active' && $connection->provider->value === 'github', 403);
        $owner = $connection->user;
        abort_unless($owner && app(ProjectAccess::class)->allows($owner, $project, 'integration.manage'), 403);
        $secret = $connection->credentials_encrypted['webhook_secret'] ?? null;
        $signature = $request->header('X-Hub-Signature-256', '');
        abort_unless($secret && hash_equals('sha256='.hash_hmac('sha256', $raw, $secret), $signature), 403);
        $delivery = $request->header('X-GitHub-Delivery', '');
        $event = $request->header('X-GitHub-Event', '');
        abort_unless(preg_match('/^[a-zA-Z0-9-]{1,100}$/D', $delivery) && in_array($event, ['push', 'ping'], true), 422);
        $input = json_decode($raw, true, 32);
        abort_unless(is_array($input) && isset($input['repository']['id']) && (string) $input['repository']['id'] === $connection->account_ref, 422);
        $commits = $input['commits'] ?? [];
        abort_unless(is_array($commits) && count($commits) <= 100, 422);
        DB::transaction(function () use ($project, $connection, $delivery, $event, $commits, $raw, $signature, $input) {
            Workspace::lockForUpdate()->findOrFail($project->workspace_id);
            $project = $project->fresh();
            $current = $project->githubConnection;
            abort_unless($current && $current->id === $connection->id && $current->status->value === 'active' && $current->user && app(ProjectAccess::class)->allows($current->user, $project, 'integration.manage'), 403);
            $currentSecret = $current->credentials_encrypted['webhook_secret'] ?? '';
            abort_unless($currentSecret && hash_equals('sha256='.hash_hmac('sha256', $raw, $currentSecret), $signature) && $current->account_ref === (string) $input['repository']['id'], 403);

            if (GithubLog::where('project_id', $project->id)->where('integration_connection_id', $connection->id)->where('delivery_id', $delivery)->exists()) {
                return;
            }
            $safe = [];
            foreach ($commits as $commit) {
                if (! is_array($commit) || ! is_string($commit['message'] ?? null) || ! is_string($commit['id'] ?? null) || ! preg_match('/^[a-f0-9]{7,64}$/iD', $commit['id'] ?? '')) {
                    throw ValidationException::withMessages(['commits' => 'Commit không hợp lệ.']);
                }
                $safe[] = ['id' => $commit['id'], 'message' => mb_substr($commit['message'], 0, 2000)];
            }
            $log = GithubLog::create(['project_id' => $project->id, 'integration_connection_id' => $connection->id, 'delivery_id' => $delivery, 'event_type' => $event, 'repository_external_id' => $connection->account_ref, 'summary' => $event.' · '.count($safe).' commit', 'payload' => ['commits' => $safe], 'status' => 'processed', 'attempt_count' => 1, 'received_at' => now(), 'processed_at' => now()]);
            // Match only explicit [CODE] markers. Never change task state from a commit message.
            foreach ($safe as $commit) {
                preg_match_all('/\[([A-Z0-9][A-Z0-9_-]{0,39})\]/', $commit['message'], $matches);
                foreach (array_unique($matches[1]) as $code) {
                    $task = $project->tasks()->where('code', $code)->first();
                    if (! $task) {
                        continue;
                    }
                    TaskActivity::create(['task_id' => $task->id, 'actor_id' => null, 'type' => 'HISTORY', 'source' => 'github', 'event_type' => 'github.commit', 'data' => ['github_log_id' => $log->id, 'commit' => $commit['id'], 'message' => $commit['message']]]);
                }
            }
        }, 3);
    }
}
