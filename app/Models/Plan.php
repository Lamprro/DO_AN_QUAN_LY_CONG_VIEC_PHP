<?php

namespace App\Models;

use App\Enums\PlanStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends DomainModel
{
    public const UPDATED_AT = null;

    protected $fillable = ['plan_group_id', 'parent_plan_id', 'version_no', 'workspace_id', 'project_id', 'ai_run_id', 'name', 'data', 'status', 'is_active', 'validation_errors', 'change_summary', 'created_by', 'reviewed_by', 'reviewed_at', 'applied_at'];

    protected $hidden = ['active_group_key'];

    protected $attributes = [
        'status' => 'draft',
        'is_active' => false,
        'version_no' => 1,
    ];

    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'data' => 'array',
            'is_active' => 'boolean',
            'validation_errors' => 'array',
            'created_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'applied_at' => 'immutable_datetime',
            'status' => PlanStatus::class,
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'parent_plan_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Plan::class, 'parent_plan_id');
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'plan_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class, 'plan_id');
    }

    /** Query filter only; callers must still authorize access. */
    public function scopeForWorkspace(Builder $query, string $workspaceId): Builder
    {
        return $query->where($this->qualifyColumn('workspace_id'), $workspaceId);
    }
}
