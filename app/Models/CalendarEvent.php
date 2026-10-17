<?php

namespace App\Models;

use App\Enums\CalendarEventStatus;
use App\Enums\CalendarEventType;
use App\Enums\CalendarVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarEvent extends DomainModel
{
    protected $fillable = ['user_id', 'workspace_id', 'project_id', 'task_id', 'plan_id', 'event_type', 'title', 'description', 'starts_at', 'ends_at', 'timezone', 'all_day', 'is_busy', 'visibility', 'status', 'participants', 'integration_connection_id', 'external_id', 'source'];

    protected $hidden = [];

    protected $attributes = [
        'event_type' => 'NOTE',
        'timezone' => 'Asia/Ho_Chi_Minh',
        'all_day' => false,
        'is_busy' => false,
        'visibility' => 'private',
        'status' => 'planned',
        'source' => 'manual',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'all_day' => 'boolean',
            'is_busy' => 'boolean',
            'participants' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'event_type' => CalendarEventType::class,
            'status' => CalendarEventStatus::class,
            'visibility' => CalendarVisibility::class,
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

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function integrationConnection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class, 'calendar_event_id');
    }

    /** Query filter only; callers must still authorize access. */
    public function scopeForWorkspace(Builder $query, string $workspaceId): Builder
    {
        return $query->where($this->qualifyColumn('workspace_id'), $workspaceId);
    }
}
