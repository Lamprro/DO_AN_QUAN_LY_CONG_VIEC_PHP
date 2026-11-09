<?php

namespace App\Models;

use App\Enums\CalendarEventStatus;
use App\Enums\CalendarEventType;
use App\Enums\CalendarVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One table for calendar events and team-calendar proposals.
 *
 * A proposal is a row with proposed_by set. user_id is always the owner of the
 * (future) event, i.e. the proposal recipient. While the row is in a
 * proposal-only status (proposed/declined/withdrawn) it is hidden by the
 * global scope ON_CALENDAR_SCOPE, so calendar, busy-slot, planning and report
 * queries never see it. Accepting flips the status to planned and the row
 * becomes the recipient's ordinary event (no copy).
 */
class CalendarEvent extends DomainModel
{
    public const ON_CALENDAR_SCOPE = 'on_calendar';

    protected $fillable = ['user_id', 'workspace_id', 'project_id', 'task_id', 'plan_id', 'event_type', 'title', 'description', 'starts_at', 'ends_at', 'timezone', 'all_day', 'is_busy', 'visibility', 'status', 'participants', 'integration_connection_id', 'external_id', 'source', 'proposed_by', 'responded_at'];

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

    protected static function booted(): void
    {
        static::addGlobalScope(self::ON_CALENDAR_SCOPE, fn (Builder $query) => $query->whereNotIn($query->qualifyColumn('status'), CalendarEventStatus::proposalOnlyValues()));
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'responded_at' => 'immutable_datetime',
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

    /** Alias of user() for proposal rows: the person who must accept. */
    public function recipient(): BelongsTo
    {
        return $this->user();
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
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

    /** Proposal ledger: every row created from Team Calendar, in any status. */
    public function scopeProposals(Builder $query): Builder
    {
        return $query->withoutGlobalScope(self::ON_CALENDAR_SCOPE)->whereNotNull($this->qualifyColumn('proposed_by'));
    }

    public function isProposal(): bool
    {
        return $this->proposed_by !== null;
    }

    public function isPendingProposal(): bool
    {
        return $this->isProposal() && $this->status === CalendarEventStatus::PROPOSED;
    }
}
