@if($isPersonalOverview ?? false)
    <small class="task-code-line" data-task-source="{{ $sourceTask->id }}">
        {{ $sourceTask->workspace->name }} / {{ $sourceTask->project?->name ?? 'Việc riêng' }}
        @if($sourceTask->project?->archived_at) · Dự án đã lưu trữ @endif
    </small>
@endif
