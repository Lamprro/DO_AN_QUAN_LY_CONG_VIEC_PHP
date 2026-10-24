<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Models\Concerns\HasEditRevision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends DomainModel
{
    use HasEditRevision;

    protected $fillable = ['workspace_id', 'parent_project_id', 'name', 'description', 'created_by', 'status', 'start_date', 'deadline', 'archived_at', 'github_connection_id', 'github_config'];

    protected $hidden = [];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'deadline' => 'immutable_date',
            'archived_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'github_config' => 'array',
            'status' => ProjectStatus::class,
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'parent_project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function githubConnection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'github_connection_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Project::class, 'parent_project_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class, 'project_id');
    }

    /** Relation name used by scoped {projectMember} binding. */
    public function projectMembers(): HasMany
    {
        return $this->members();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class, 'project_id');
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'project_id');
    }

    public function githubLogs(): HasMany
    {
        return $this->hasMany(GithubLog::class, 'project_id');
    }

    /** Query filter only; callers must still authorize access. */
    public function scopeForWorkspace(Builder $query, string $workspaceId): Builder
    {
        return $query->where($this->qualifyColumn('workspace_id'), $workspaceId);
    }

    public function taskTemplates(): HasMany
    {
        return $this->hasMany(TaskTemplate::class, 'project_id');
    }

    public function calendarProposals(): HasMany
    {
        return $this->hasMany(CalendarProposal::class, 'project_id');
    }
}
