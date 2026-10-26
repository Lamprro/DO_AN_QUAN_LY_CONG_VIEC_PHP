<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\Workspace;
use App\Support\EditRevision;
use App\Support\ProjectAccess;
use App\Support\WorkspaceAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    public function __construct(private ProjectAccess $access, private WorkspaceAccess $workspaces) {}

    public function save(User $actor, Workspace $workspace, array $data, ?Project $project = null): Project
    {
        return DB::transaction(function () use ($actor, $workspace, $data, $project) {
            $workspace = Workspace::lockForUpdate()->findOrFail($workspace->id);
            if ($project) {
                $project = $workspace->projects()->lockForUpdate()->findOrFail($project->id);
                Gate::forUser($actor)->authorize('update', $project);
                EditRevision::check($project, $data);
            } else {
                abort_unless($this->access->canCreate($actor, $workspace), 403);
            }
            $data = array_intersect_key($data, array_flip(['name', 'description', 'status', 'start_date', 'deadline', 'parent_project_id']));
            if ($parentId = $data['parent_project_id'] ?? null) {
                $parent = $workspace->projects()->findOrFail($parentId);
                Gate::forUser($actor)->authorize('view', $parent);
                if ($parent->archived_at) {
                    throw ValidationException::withMessages(['parent_project_id' => 'Không chọn project cha đã lưu trữ.']);
                }
            }
            if (($data['status'] ?? null) === 'completed' && $project && ($project->tasks()->where('task_type', '!=', 'GROUP')->whereNotIn('status', ['DONE', 'CANCELLED'])->exists() || $project->children()->whereNotIn('status', ['completed', 'cancelled'])->exists())) {
                throw ValidationException::withMessages(['status' => 'Chưa thể hoàn tất project khi còn task hoặc project con chưa hoàn tất/hủy.']);
            }
            if ($project) {
                $project->update($data);

                return $project;
            }
            $project = Project::create($data + ['workspace_id' => $workspace->id, 'created_by' => $actor->id]);
            $membership = $this->workspaces->membership($actor, $workspace);
            ProjectMember::create(['project_id' => $project->id, 'workspace_member_id' => $membership->id, 'role_id' => $membership->role_id, 'status' => 'active', 'joined_at' => now()]);

            return $project;
        });
    }

    public function archive(User $actor, Workspace $workspace, Project $project, bool $restore = false): void
    {
        DB::transaction(function () use ($actor, $workspace, $project, $restore) {
            Workspace::lockForUpdate()->findOrFail($workspace->id);
            $project = $workspace->projects()->lockForUpdate()->findOrFail($project->id);
            Gate::forUser($actor)->authorize('archive', $project);
            $project->update(['archived_at' => $restore ? null : now()]);
        });
    }

    public function delete(User $actor, Workspace $workspace, Project $project): void
    {
        DB::transaction(function () use ($actor, $workspace, $project) {
            Workspace::lockForUpdate()->findOrFail($workspace->id);
            $project = $workspace->projects()->lockForUpdate()->findOrFail($project->id);
            Gate::forUser($actor)->authorize('delete', $project);
            if ($project->status !== ProjectStatus::DRAFT) {
                throw ValidationException::withMessages(['project' => 'Chỉ xóa project nháp trống; hãy dùng lưu trữ.']);
            }
            foreach (['projects' => 'parent_project_id', 'tasks' => 'project_id', 'plans' => 'project_id', 'ai_runs' => 'project_id', 'calendar_events' => 'project_id', 'github_logs' => 'project_id', 'report_snapshots' => 'project_id', 'notifications' => 'project_id', 'calendar_proposals' => 'project_id', 'task_templates' => 'project_id'] as $table => $field) {
                if (Schema::hasTable($table) && DB::table($table)->where($field, $project->id)->exists()) {
                    throw ValidationException::withMessages(['project' => 'Project có dữ liệu liên quan; hãy dùng lưu trữ.']);
                }
            }
            $project->members()->delete();
            $project->delete();
        });
    }

    public function saveMember(User $actor, Workspace $workspace, Project $project, array $data, ?ProjectMember $member = null): ProjectMember
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

    public function removeMember(User $actor, Workspace $workspace, Project $project, ProjectMember $member): void
    {
        $this->saveMember($actor, $workspace, $project, ['role_id' => $member->role_id, 'status' => 'left'], $member);
    }
}
