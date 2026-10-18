<?php

namespace App\Models;

class TimeEntryAdjustment extends DomainModel
{
    protected $fillable = ['time_entry_id', 'actor_id', 'before', 'after', 'reason', 'created_at'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'immutable_datetime'];
    }

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit is immutable'));
        static::deleting(fn () => throw new \LogicException('Audit is immutable'));
    }
}
