<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMember extends DomainModel
{
    public const UPDATED_AT = null;

    public $timestamps = false;

    protected $fillable = ['project_id', 'workspace_member_id', 'role_id', 'status', 'joined_at'];

    protected $hidden = [];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'immutable_datetime',
            'status' => MembershipStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function workspaceMember(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class, 'workspace_member_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
