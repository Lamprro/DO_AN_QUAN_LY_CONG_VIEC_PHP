<?php

namespace App\Services\Integration;

use App\Authorization\ProjectAccess;
use App\Models\GithubLog;
use App\Models\IntegrationConnection;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Per-project GitHub webhook settings and delivery log. */
class GithubSettingsService
{
    public function __construct(private ProjectAccess $projects) {}

    public function page(User $actor, Workspace $workspace, Project $project): array
    {
        $this->assert($actor, $workspace, $project, 'integration.view');

        return [
            'workspace' => $workspace,
            'project' => $project,
            'connection' => $project->githubConnection,
            'latestDelivery' => GithubLog::where('project_id', $project->id)->latest('received_at')->latest('id')->first(),
            'logs' => GithubLog::where('project_id', $project->id)->latest('received_at')->paginate(20),
            'canManage' => $this->projects->allows($actor, $project, 'integration.manage'),
        ];
    }

    /** The secret is stored encrypted and never shown again; enabling needs INTEGRATIONS_ENABLED and a secret. */
    public function save(User $actor, Workspace $workspace, Project $project, array $data): void
    {
        $this->assert($actor, $workspace, $project, 'integration.manage');
        DB::transaction(function () use ($actor, $workspace, $project, $data) {
            Workspace::lockForUpdate()->findOrFail($workspace->id);
            $project = $project->fresh();
            $connection = $project->githubConnection ?? new IntegrationConnection([
                'workspace_id' => $workspace->id,
                'user_id' => $actor->id,
                'provider' => 'github',
                'scopes' => ['webhook:push'],
            ]);
            $secrets = $connection->credentials_encrypted ?? [];
            if (! empty($data['webhook_secret'])) {
                $secrets['webhook_secret'] = $data['webhook_secret'];
            }
            if ($data['enabled'] && (! config('work_management.integrations_enabled') || empty($secrets['webhook_secret']))) {
                throw ValidationException::withMessages(['enabled' => 'Cần bật INTEGRATIONS_ENABLED và cấu hình secret trước.']);
            }
            $connection->fill([
                'account_ref' => $data['repository_id'],
                'user_id' => $actor->id,
                'credentials_encrypted' => $secrets,
                'status' => $data['enabled'] ? 'active' : 'disabled',
            ])->save();
            $project->update(['github_connection_id' => $connection->id]);
        }, 3);
    }

    private function assert(User $actor, Workspace $workspace, Project $project, string $permission): void
    {
        abort_unless($project->workspace_id === $workspace->id, 404);
        abort_unless($this->projects->allows($actor, $project, $permission), 403);
    }
}
