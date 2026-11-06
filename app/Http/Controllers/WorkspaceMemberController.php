<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberRequest;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Workspace\WorkspaceQuery;
use App\Services\Workspace\WorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceMemberController extends Controller
{
    public function __construct(private WorkspaceService $service) {}

    public function index(Request $request, Workspace $workspace, WorkspaceQuery $query): View
    {
        $this->authorize('manageMembers', $workspace);

        return view('members.index', $query->members($request->user(), $workspace));
    }

    public function update(MemberRequest $request, Workspace $workspace, WorkspaceMember $member): RedirectResponse
    {
        $this->service->updateMember($request->user(), $workspace, $member, $request->validated());

        return to_route('workspaces.members.index', $workspace)->with('success', 'Đã cập nhật thành viên.');
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceMember $member): RedirectResponse
    {
        $this->service->removeMember($request->user(), $workspace, $member);

        return to_route('workspaces.members.index', $workspace)->with('success', 'Đã đánh dấu thành viên rời workspace; lịch sử được giữ lại.');
    }
}
