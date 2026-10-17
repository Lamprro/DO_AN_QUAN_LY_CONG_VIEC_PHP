<?php

namespace App\Models;

use App\Enums\AiOperation;
use App\Enums\AiRunStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiRun extends DomainModel
{
    protected $fillable = ['workspace_id', 'project_id', 'requested_by', 'operation', 'trigger_type', 'user_prompt', 'context_snapshot', 'status', 'idempotency_key', 'started_at', 'finished_at', 'error_message'];

    protected $hidden = [];

    protected $attributes = [
        'status' => 'queued',
        'context_snapshot' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'context_snapshot' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'operation' => AiOperation::class,
            'status' => AiRunStatus::class,
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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function calls(): HasMany
    {
        return $this->hasMany(AiCall::class, 'ai_run_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class, 'ai_run_id');
    }

    /** Query filter only; callers must still authorize access. */
    public function scopeForWorkspace(Builder $query, string $workspaceId): Builder
    {
        return $query->where($this->qualifyColumn('workspace_id'), $workspaceId);
    }
}
