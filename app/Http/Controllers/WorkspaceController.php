<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransferOwnerRequest;
use App\Http\Requests\WorkspaceRequest;
use App\Models\Workspace;
use App\Services\Workspace\WorkspaceQuery;
use App\Services\Workspace\WorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function index(Request $request, WorkspaceQuery $query): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:160']]);

        return view('workspaces.index', ['workspaces' => $query->index($request->user(), $request->input('q'))]);
    }

    public function create(): View
    {
        return view('workspaces.create', ['timezones' => timezone_identifiers_list()]);
    }

    public function store(WorkspaceRequest $request, WorkspaceService $service): RedirectResponse
    {
        $workspace = $service->create($request->user(), $request->validated('name'), $request->validated('timezone'));
        $request->session()->put('current_workspace_id', $workspace->id);

        return to_route('workspaces.show', $workspace)->with('success', 'Đã tạo workspace nhóm.');
    }

    public function show(Request $request, Workspace $workspace, WorkspaceQuery $query): View
    {
        $this->authorize('view', $workspace);

        return view('workspaces.show', $query->overview($request->user(), $workspace));
    }

    public function edit(Workspace $workspace): View
    {
        $this->authorize('update', $workspace);

        return view('workspaces.edit', ['workspace' => $workspace, 'timezones' => timezone_identifiers_list()]);
    }

    public function update(WorkspaceRequest $request, Workspace $workspace, WorkspaceService $service): RedirectResponse
    {
        $service->update($request->user(), $workspace, $request->validated());

        return to_route('workspaces.show', $workspace)->with('success', 'Đã cập nhật workspace.');
    }

    public function select(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('view', $workspace);
        $request->session()->put('current_workspace_id', $workspace->id);

        return to_route('workspaces.show', $workspace);
    }

    public function transfer(TransferOwnerRequest $request, Workspace $workspace, WorkspaceService $service): RedirectResponse
    {
        $service->transfer($request->user(), $workspace, $workspace->members()->findOrFail($request->validated('member_id')));

        return to_route('workspaces.show', $workspace)->with('success', 'Đã chuyển chủ sở hữu. Quyền của bạn đã được cập nhật.');
    }

    public function leave(Request $request, Workspace $workspace, WorkspaceService $service): RedirectResponse
    {
        $service->leave($request->user(), $workspace);
        $request->session()->forget('current_workspace_id');

        return to_route('workspaces.index')->with('success', 'Đã rời workspace.');
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceService $service): RedirectResponse
    {
        $service->delete($request->user(), $workspace);
        $request->session()->forget('current_workspace_id');

        return to_route('workspaces.index')->with('success', 'Đã xóa workspace trống.');
    }
}
