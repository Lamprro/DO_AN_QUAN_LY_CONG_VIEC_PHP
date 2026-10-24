<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarProposal extends DomainModel
{
    protected $fillable = ['workspace_id', 'project_id', 'task_id', 'proposed_by', 'recipient_id', 'calendar_event_id', 'title', 'description', 'event_type', 'starts_at', 'ends_at', 'timezone', 'visibility', 'status', 'revision', 'responded_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'responded_at' => 'immutable_datetime', 'revision' => 'integer'];
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

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }
}
