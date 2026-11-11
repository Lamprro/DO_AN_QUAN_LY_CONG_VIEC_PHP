<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Workspace;
use App\Services\Integration\GithubSettingsService;
use App\Services\Integration\GithubWebhookService;
use Illuminate\Http\Request;

class GithubIntegrationController extends Controller
{
    public function show(Request $request, Workspace $workspace, Project $project, GithubSettingsService $settings)
    {
        return view('integrations.github', $settings->page($request->user(), $workspace, $project));
    }

    public function save(Request $request, Workspace $workspace, Project $project, GithubSettingsService $settings)
    {
        $validated = $request->validate([
            'repository_id' => 'required|regex:/^[0-9]{1,20}$/',
            'webhook_secret' => 'nullable|string|min:32|max:200',
            'enabled' => 'required|boolean',
        ]);
        $settings->save($request->user(), $workspace, $project, $validated);

        return back()->with('success', 'Đã lưu cấu hình. Secret không hiển thị lại.');
    }

    /** Public webhook endpoint; the signature is verified in the service. */
    public function receive(Request $request, Project $project, GithubWebhookService $service)
    {
        $service->receive($project, $request->getContent(), $request->header('X-Hub-Signature-256', ''), $request->header('X-GitHub-Delivery', ''), $request->header('X-GitHub-Event', ''));

        return response('', 204);
    }
}
