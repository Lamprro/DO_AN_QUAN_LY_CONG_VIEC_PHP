<?php

namespace App\Models;

use App\Enums\TaskReminderKind;
use App\Enums\TaskReminderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Delivery ledger for due/review reminders: one row per (user, task, kind, local day, source). */
class TaskReminder extends DomainModel
{
    protected $fillable = ['workspace_id', 'task_id', 'user_id', 'kind', 'local_date', 'timezone', 'source_token', 'review_after_minutes', 'dedupe_key', 'status', 'skip_reason', 'sent_at'];

    protected $hidden = [];

    protected $attributes = [
        'review_after_minutes' => 60,
        'status' => 'queued',
    ];

    protected function casts(): array
    {
        return [
            'local_date' => 'immutable_date',
            'review_after_minutes' => 'integer',
            'sent_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'kind' => TaskReminderKind::class,
            'status' => TaskReminderStatus::class,
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Query filter only; callers must still authorize access. */
    public function scopeForWorkspace(Builder $query, string $workspaceId): Builder
    {
        return $query->where($this->qualifyColumn('workspace_id'), $workspaceId);
    }
}
