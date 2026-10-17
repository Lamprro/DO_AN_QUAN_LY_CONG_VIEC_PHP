<?php

namespace App\Models;

use App\Enums\WorkspaceKind;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends DomainModel
{
    protected $fillable = ['name', 'kind', 'owner_id', 'timezone'];

    protected $hidden = [];

    protected $attributes = [
        'kind' => 'team',
        'timezone' => 'Asia/Ho_Chi_Minh',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'kind' => WorkspaceKind::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'workspace_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class, 'workspace_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'workspace_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'workspace_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class, 'workspace_id');
    }

    public function integrationConnections(): HasMany
    {
        return $this->hasMany(IntegrationConnection::class, 'workspace_id');
    }
}
