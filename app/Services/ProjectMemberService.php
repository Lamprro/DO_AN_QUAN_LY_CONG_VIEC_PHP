<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\Workspace;
use App\Support\ProjectAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProjectMemberService
{
    public function __construct(private ProjectAccess $access) {}

    public function save(User $actor, Workspace $workspace, Project $project, array $data, ?ProjectMember $member = null): ProjectMember
    {
        return DB::transaction(function () use ($actor, $workspace, $project, $data, $member) {
            $workspace = Workspace::lockForUpdate()->findOrFail($workspace->id);
            $project = $workspace->projects()->lockForUpdate()->findOrFail($project->id);
            Gate::forUser($actor)->authorize('manageMembers', $project);
            if ($member) {
                $member = $project->members()->with('role')->lockForUpdate()->findOrFail($member->id);
                $this->access->grantable($actor, $project, $member->role->permissions);
                $wm = $workspace->members()->with('user')->findOrFail($member->workspace_member_id);
            } else {
                $wm = $workspace->members()->with('user')->findOrFail($data['workspace_member_id']);
                $member = $project->members()->with('role')->where('workspace_member_id', $wm->id)->lockForUpdate()->first();
                if ($member && $member->status->value !== 'left') {
                    throw ValidationException::withMessages(['workspace_member_id' => 'Người này đã là thành viên project; dùng form cập nhật.']);
                }
                if ($member) {
                    $this->access->grantable($actor, $project, $member->role->permissions);
                }
            }
            if ($wm->user_id === $workspace->owner_id || $wm->user_id === $actor->id) {
                throw ValidationException::withMessages(['member' => 'Không sửa quyền Owner workspace hoặc chính mình qua quản lý project.']);
            }
            if ($data['status'] === 'active' && ($wm->status->value !== 'active' || $wm->user->status->value !== 'active')) {
                throw ValidationException::withMessages(['workspace_member_id' => 'Cần tài khoản và membership workspace đang hoạt động.']);
            }
            $role = $workspace->roles()->findOrFail($data['role_id']);
            if ($role->is_system && $role->name === 'Owner') {
                throw ValidationException::withMessages(['role_id' => 'Owner dành cho chủ workspace; chọn role khác.']);
            }
            $this->access->grantable($actor, $project, $role->permissions);
            $values = ['role_id' => $role->id, 'status' => $data['status']];
            if ($member) {
                $member->update($values);

                return $member;
            }

            return ProjectMember::create($values + ['project_id' => $project->id, 'workspace_member_id' => $wm->id, 'joined_at' => now()]);
        });
    }

    public function remove(User $actor, Workspace $workspace, Project $project, ProjectMember $member): void
    {
        $this->save($actor, $workspace, $project, ['role_id' => $member->role_id, 'status' => 'left'], $member);
    }
}
