<?php

namespace App\Models;

use App\Enums\ReportScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSnapshot extends DomainModel
{
    public const UPDATED_AT = null;

    protected $fillable = ['workspace_id', 'project_id', 'subject_user_id', 'generated_by', 'ai_run_id', 'scope', 'period_start', 'period_end', 'metrics_json', 'ai_commentary', 'storage_key'];

    protected $hidden = [];

    protected $attributes = [

    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_datetime',
            'period_end' => 'immutable_datetime',
            'metrics_json' => 'array',
            'created_at' => 'immutable_datetime',
            'scope' => ReportScope::class,
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

    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }

    /** Query filter only; callers must still authorize access. */
    public function scopeForWorkspace(Builder $query, string $workspaceId): Builder
    {
        return $query->where($this->qualifyColumn('workspace_id'), $workspaceId);
    }
}
