<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransferOwnerRequest;
use App\Http\Requests\WorkspaceRequest;
use App\Models\Workspace;
use App\Services\MemberService;
use App\Services\WorkspaceService;
use App\Support\ProjectAccess;
use App\Support\SearchTerm;
use App\Support\WorkspaceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:160']]);
        $user = $request->user();
        $workspaces = Workspace::query()->whereHas('members', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))
            ->where(fn ($q) => $q->where('owner_id', $user->id)->orWhereHas('members', fn ($m) => $m->where('user_id', $user->id)->where('status', 'active')->whereHas('role', fn ($r) => $r->whereJsonContains('permissions', 'workspace.view'))))
            ->when($request->filled('q'), fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [SearchTerm::contains($request->input('q'))]))->latest()->paginate(12)->withQueryString();

        return view('workspaces.index', compact('workspaces'));
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

    public function show(Request $request, Workspace $workspace, WorkspaceAccess $access): View
    {
        $this->authorize('view', $workspace);
        $workspace->load('owner')->loadCount('members', 'roles', 'projects', 'tasks');
        $visible = app(ProjectAccess::class)->visible($request->user(), $workspace)->get();
        $workspace->setAttribute('projects_count', $visible->count());
        $taskProjects = $visible->filter(fn ($p) => app(ProjectAccess::class)->allows($request->user(), $p, 'task.view'))->pluck('id');
        $workspace->setAttribute('tasks_count', $workspace->tasks()->where(function ($q) use ($taskProjects, $workspace, $request) {
            $q->whereIn('project_id', $taskProjects);
            if ($workspace->kind->value === 'personal' && $workspace->owner_id === $request->user()->id) {
                $q->orWhereNull('project_id');
            }
        })->count());

        return view('workspaces.show', ['workspace' => $workspace, 'membership' => $access->membership($request->user(), $workspace), 'transferCandidates' => $workspace->owner_id === $request->user()->id ? $workspace->members()->with('user')->where('status', 'active')->where('user_id', '!=', $request->user()->id)->whereHas('user', fn ($q) => $q->where('status', 'active'))->get() : collect()]);
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

    public function leave(Request $request, Workspace $workspace, MemberService $service): RedirectResponse
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
