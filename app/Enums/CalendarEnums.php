<?php

namespace App\Enums;

/**
 * Lifecycle of a calendar_events row.
 * Calendar states: planned, completed, cancelled.
 * Proposal states (row created from Team Calendar, never shown on any calendar):
 * proposed -> planned (accepted) | declined (by recipient) | withdrawn (by proposer).
 */
enum CalendarEventStatus: string
{
    case PROPOSED = 'proposed';
    case PLANNED = 'planned';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case DECLINED = 'declined';
    case WITHDRAWN = 'withdrawn';

    /** States that exist only on the proposal ledger and must never reach calendar/busy/report queries. */
    public static function proposalOnlyValues(): array
    {
        return [self::PROPOSED->value, self::DECLINED->value, self::WITHDRAWN->value];
    }

    /** States a user may choose on the normal calendar form. */
    public static function calendarValues(): array
    {
        return [self::PLANNED->value, self::COMPLETED->value, self::CANCELLED->value];
    }

    public function isProposalOnly(): bool
    {
        return in_array($this->value, self::proposalOnlyValues(), true);
    }
}

enum CalendarEventType: string
{
    case NOTE = 'NOTE';
    case MEETING = 'MEETING';
    case BUSY = 'BUSY';
    case EXTRA_AVAILABLE = 'EXTRA_AVAILABLE';
    case TASK_BLOCK = 'TASK_BLOCK';
}

enum CalendarVisibility: string
{
    case PRIVATE = 'private';
    case WORKSPACE = 'workspace';
    case PROJECT = 'project';
}
