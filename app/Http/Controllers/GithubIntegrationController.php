<?php

namespace App\Http\Controllers;

use App\Models\GithubLog;
use App\Models\IntegrationConnection;
use App\Models\Project;
use App\Models\Workspace;
use App\Services\GithubWebhookService;
use App\Support\ProjectAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GithubIntegrationController extends Controller
{
    private function bound(Request $r, Workspace $workspace, Project $project, string $permission): void
    {
        abort_unless($project->workspace_id === $workspace->id, 404);
        abort_unless(app(ProjectAccess::class)->allows($r->user(), $project, $permission), 403);
    }

    public function show(Request $r, Workspace $workspace, Project $project)
    {
        $this->bound($r, $workspace, $project, 'integration.view');
        $connection = $project->githubConnection;
        $latestDelivery = GithubLog::where('project_id', $project->id)->latest('received_at')->latest('id')->first();
        $logs = GithubLog::where('project_id', $project->id)->latest('received_at')->paginate(20);

        return view('integrations.github', compact('workspace', 'project', 'connection', 'logs', 'latestDelivery'));
    }

    public function save(Request $r, Workspace $workspace, Project $project)
    {
        $this->bound($r, $workspace, $project, 'integration.manage');
        $v = $r->validate(['repository_id' => 'required|regex:/^[0-9]{1,20}$/', 'webhook_secret' => 'nullable|string|min:32|max:200', 'enabled' => 'required|boolean']);
        DB::transaction(function () use ($r, $workspace, $project, $v) {
            Workspace::lockForUpdate()->findOrFail($workspace->id);
            $project = $project->fresh();
            $connection = $project->githubConnection;
            if (! $connection) {
                $connection = new IntegrationConnection(['workspace_id' => $workspace->id, 'user_id' => $r->user()->id, 'provider' => 'github', 'scopes' => ['webhook:push']]);
            }
            $secrets = $connection->credentials_encrypted ?? [];
            if (! empty($v['webhook_secret'])) {
                $secrets['webhook_secret'] = $v['webhook_secret'];
            }
            if ($v['enabled'] && (! config('work_management.integrations_enabled') || empty($secrets['webhook_secret']))) {
                throw ValidationException::withMessages(['enabled' => 'Cần bật INTEGRATIONS_ENABLED và cấu hình secret trước.']);
            }
            $connection->fill(['account_ref' => $v['repository_id'], 'user_id' => $r->user()->id, 'credentials_encrypted' => $secrets, 'status' => $v['enabled'] ? 'active' : 'disabled'])->save();
            $project->update(['github_connection_id' => $connection->id]);
        }, 3);

        return back()->with('success', 'Đã lưu cấu hình. Secret không hiển thị lại.');
    }

    public function receive(Request $r, Project $project, GithubWebhookService $service)
    {
        $service->receive($r, $project);

        return response('', 204);
    }
}
