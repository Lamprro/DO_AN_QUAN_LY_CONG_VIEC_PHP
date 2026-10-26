<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberRequest;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\InvitationService;
use App\Services\WorkspaceService;
use App\Support\WorkspaceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceMemberController extends Controller
{
    public function index(Request $request, Workspace $workspace, WorkspaceAccess $access, InvitationService $invitations): View
    {
        $this->authorize('manageMembers', $workspace);
        $permissions = $access->permissions($request->user(), $workspace);
        $roles = $workspace->roles()->orderBy('name')->get()->filter(fn ($r) => ! ($r->is_system && $r->name === 'Owner') && ! array_diff($r->permissions, $permissions));

        return view('members.index', ['workspace' => $workspace, 'members' => $workspace->members()->with('user', 'role')->orderBy('joined_at')->get(), 'assignableRoles' => $roles, 'pendingInvitations' => $invitations->pending($workspace), 'actorPermissions' => $permissions]);
    }

    public function update(MemberRequest $request, Workspace $workspace, WorkspaceMember $member, WorkspaceService $service): RedirectResponse
    {
        $service->updateMember($request->user(), $workspace, $member, $request->validated());

        return to_route('workspaces.members.index', $workspace)->with('success', 'Đã cập nhật thành viên.');
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceMember $member, WorkspaceService $service): RedirectResponse
    {
        $service->removeMember($request->user(), $workspace, $member);

        return to_route('workspaces.members.index', $workspace)->with('success', 'Đã đánh dấu thành viên rời workspace; lịch sử được giữ lại.');
    }
}
