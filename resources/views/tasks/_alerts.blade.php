@php
    $isOverdue = $task->deadline_at?->isPast() && !in_array($task->status->value, ['DONE', 'CANCELLED']);
    $hasOpenBlocker = ($task->relationLoaded('blockers') ? $task->blockers : $task->blockers()->where('status', 'open')->get())->where('status', 'open')->isNotEmpty();
    $dependencies = $task->relationLoaded('incomingDependencies') ? $task->incomingDependencies : $task->incomingDependencies()->with('predecessor')->get();
    $isWaitingPredecessor = $dependencies->contains(fn($d) => $d->predecessor && $d->predecessor->status->value !== 'DONE');
@endphp
@if($isOverdue || $hasOpenBlocker || $isWaitingPredecessor)
    <div class="task-impact-alerts" style="display: inline-flex; align-items: center; gap: 4px; flex-wrap: wrap; margin-bottom: 4px;">
        @if($isOverdue)
            <span class="task-alert-pill alert-overdue" style="background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; font-size: 10.5px; font-weight: 750; padding: 1px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 3px;" title="Công việc chưa hoàn thành nhưng đã quá hạn chót!">
                ⚠️ Quá hạn
            </span>
        @endif
        @if($hasOpenBlocker)
            <span class="task-alert-pill alert-blocker" style="background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; font-size: 10.5px; font-weight: 750; padding: 1px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 3px;" title="Công việc đang có trở ngại (Blocker) chưa được giải quyết!">
                🛑 Blocker đang mở
            </span>
        @endif
        @if($isWaitingPredecessor)
            <span class="task-alert-pill alert-predecessor" style="background: #fff7ed; color: #c2410c; border: 1px solid #fdba74; font-size: 10.5px; font-weight: 750; padding: 1px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 3px;" title="Đang chờ công việc tiền nhiệm hoàn thành trước!">
                ⏳ Chờ công việc tiền nhiệm
            </span>
        @endif
    </div>
@endif
