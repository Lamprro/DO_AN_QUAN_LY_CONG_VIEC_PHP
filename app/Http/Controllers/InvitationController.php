<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvitationRequest;
use App\Models\Workspace;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function store(InvitationRequest $request, Workspace $workspace, InvitationService $service): RedirectResponse
    {
        $result = $service->issue($request->user(), $workspace, $request->validated());
        $response = to_route('workspaces.members.index', $workspace)->with('success', 'Đã tạo lời mời có thời hạn.')->with('invitation_url', $result['url']);
        if (! $result['mail_sent']) {
            $response->with('warning', 'Chưa gửi được email; bạn có thể sao chép liên kết mời bên dưới.');
        }

        return $response;
    }

    public function show(Request $request, string $token, InvitationService $service): View
    {
        $invitation = $service->find($token);
        if (! $request->user()) {
            $request->session()->put('url.intended', route('invitations.show', $token));
        }

        return view('invitations.show', compact('invitation', 'token'));
    }

    public function accept(Request $request, string $token, InvitationService $service): RedirectResponse
    {
        $workspace = $service->accept($request->user(), $token);
        $request->session()->put('current_workspace_id', $workspace->id);

        return to_route('workspaces.show', $workspace)->with('success', 'Đã tham gia workspace.');
    }

    public function destroy(Request $request, Workspace $workspace, string $invitation, InvitationService $service): RedirectResponse
    {
        $service->revoke($request->user(), $workspace, $invitation);

        return to_route('workspaces.members.index', $workspace)->with('success', 'Đã thu hồi lời mời.');
    }
}
