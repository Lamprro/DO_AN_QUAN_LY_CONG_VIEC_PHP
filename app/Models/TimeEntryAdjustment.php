<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable audit row for a manual correction of a time entry (before/after snapshot + reason). */
class TimeEntryAdjustment extends DomainModel
{
    public const UPDATED_AT = null;

    protected $fillable = ['time_entry_id', 'actor_id', 'before', 'after', 'reason', 'created_at'];

    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit is immutable'));
        static::deleting(fn () => throw new \LogicException('Audit is immutable'));
    }

    public function timeEntry(): BelongsTo
    {
        return $this->belongsTo(TimeEntry::class, 'time_entry_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
