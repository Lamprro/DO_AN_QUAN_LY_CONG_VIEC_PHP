<?php

namespace App\Services;

use App\Models\Task;

class DeadlineInsight
{
    public function build(Task $task): array
    {
        $overdue = $task->task_type->value === 'WORK' && ! $task->archived_at && ! in_array($task->status->value, ['DONE', 'CANCELLED'], true) && $task->deadline_at?->isPast();
        $conditions = [];
        if ($task->blockers()->where('status', 'open')->exists()) {
            $conditions[] = 'Có blocker đang mở';
        }
        foreach ($task->incomingDependencies()->with('predecessor')->get() as $edge) {
            $pre = $edge->predecessor;
            if ($pre->status->value !== 'DONE' || ! $pre->completed_at || $pre->completed_at->addMinutes($edge->lag_minutes)->isFuture()) {
                $conditions[] = 'Đang chờ '.$pre->code.' hoàn tất và đủ FS/lag';
            }
        }
        if ($task->status->value === 'REVIEW') {
            $conditions[] = 'Đang chờ duyệt';
        }if (! $task->assignee_id) {
            $conditions[] = 'Chưa phân công';
        }

        return ['overdue' => (bool) $overdue, 'conditions' => $conditions, 'history' => $task->activities()->with('actor')->where('event_type', 'task.deadline_changed')->latest('created_at')->latest('id')->limit(20)->get()];
    }
}
