<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Concerns\HasEditRevision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends DomainModel
{
    use HasEditRevision;

    protected $fillable = ['workspace_id', 'project_id', 'parent_task_id', 'task_type', 'group_label', 'sort_order', 'code', 'title', 'description', 'assignee_id', 'reviewer_id', 'created_by', 'status', 'priority', 'estimate_minutes', 'deadline_at', 'started_at', 'completed_at', 'archived_at'];

    protected $hidden = [];

    protected $attributes = [
        'task_type' => 'WORK',
        'sort_order' => 0,
        'status' => 'TODO',
        'priority' => 'medium',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'estimate_minutes' => 'integer',
            'deadline_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'task_type' => TaskType::class,
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class, 'task_id');
    }

    public function blockers(): HasMany
    {
        return $this->hasMany(TaskBlocker::class, 'task_id');
    }

    public function incomingDependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'successor_task_id');
    }

    public function outgoingDependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'predecessor_task_id');
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'task_id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class, 'task_id');
    }

    /** Query filter only; callers must still authorize access. */
    public function scopeForWorkspace(Builder $query, string $workspaceId): Builder
    {
        return $query->where($this->qualifyColumn('workspace_id'), $workspaceId);
    }

    public function scopeWorkLeaves(Builder $query): Builder
    {
        return $query->where('task_type', TaskType::WORK)->whereDoesntHave('children');
    }

    public function watchers(): HasMany
    {
        return $this->hasMany(TaskWatcher::class, 'task_id');
    }

    public function calendarProposals(): HasMany
    {
        return $this->hasMany(CalendarProposal::class, 'task_id');
    }
}
