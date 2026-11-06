<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Workspace;
use App\Services\Integration\GithubSettingsService;
use App\Services\Integration\GithubWebhookService;
use Illuminate\Http\Request;

class GithubIntegrationController extends Controller
{
    public function show(Request $r, Workspace $workspace, Project $project, GithubSettingsService $settings)
    {
        return view('integrations.github', $settings->page($r->user(), $workspace, $project));
    }

    public function save(Request $r, Workspace $workspace, Project $project, GithubSettingsService $settings)
    {
        $v = $r->validate(['repository_id' => 'required|regex:/^[0-9]{1,20}$/', 'webhook_secret' => 'nullable|string|min:32|max:200', 'enabled' => 'required|boolean']);
        $settings->save($r->user(), $workspace, $project, $v);

        return back()->with('success', 'Đã lưu cấu hình. Secret không hiển thị lại.');
    }

    /** Public webhook endpoint; the signature is verified in the service. */
    public function receive(Request $r, Project $project, GithubWebhookService $service)
    {
        $service->receive($r, $project);

        return response('', 204);
    }
}
