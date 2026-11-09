<?php

namespace App\Models;

use App\Enums\TaskTemplateFrequency;
use App\Enums\TaskTemplateStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Snapshot of a task tree (definition) that can be instantiated manually or on a recurring schedule. */
class TaskTemplate extends DomainModel
{
    protected $fillable = ['workspace_id', 'project_id', 'created_by', 'name', 'definition', 'frequency', 'interval', 'starts_on', 'ends_on', 'local_time', 'timezone', 'deadline_offset_days', 'next_run_at', 'status', 'revision', 'last_error'];

    protected $hidden = [];

    protected $attributes = [
        'interval' => 1,
        'status' => 'paused',
        'revision' => 1,
    ];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'interval' => 'integer',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'deadline_offset_days' => 'integer',
            'next_run_at' => 'immutable_datetime',
            'revision' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'frequency' => TaskTemplateFrequency::class,
            'status' => TaskTemplateStatus::class,
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(TaskTemplateRun::class, 'task_template_id');
    }

    /** Query filter only; callers must still authorize access. */
    public function scopeForWorkspace(Builder $query, string $workspaceId): Builder
    {
        return $query->where($this->qualifyColumn('workspace_id'), $workspaceId);
    }
}
