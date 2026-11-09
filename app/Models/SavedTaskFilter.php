<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Personal named task filter, scoped to one workspace and optionally one project. */
class SavedTaskFilter extends DomainModel
{
    protected $fillable = ['user_id', 'workspace_id', 'project_id', 'name', 'query'];

    protected $hidden = [];

    protected $attributes = [
        'query' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'query' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** Query filter only; callers must still authorize access. */
    public function scopeForWorkspace(Builder $query, string $workspaceId): Builder
    {
        return $query->where($this->qualifyColumn('workspace_id'), $workspaceId);
    }
}
