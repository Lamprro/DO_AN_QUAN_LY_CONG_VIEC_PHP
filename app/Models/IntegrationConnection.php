<?php

namespace App\Models;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationConnection extends DomainModel
{
    protected $fillable = ['workspace_id', 'user_id', 'provider', 'account_ref', 'credentials_encrypted', 'scopes', 'status', 'expires_at'];

    protected $hidden = ['credentials_encrypted'];

    protected $attributes = [
        'status' => 'disabled',
        'scopes' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'provider' => IntegrationProvider::class,
            'status' => IntegrationStatus::class,
            'credentials_encrypted' => 'encrypted:array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function githubLogs(): HasMany
    {
        return $this->hasMany(GithubLog::class, 'integration_connection_id');
    }

    /** Query filter only; callers must still authorize access. */
    public function scopeForWorkspace(Builder $query, string $workspaceId): Builder
    {
        return $query->where($this->qualifyColumn('workspace_id'), $workspaceId);
    }
}
