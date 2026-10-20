<x-layout :title="$event->title">
<div style="max-width: 760px; margin: 0 auto 2rem;">
    <div class="breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: 0.5rem;">
        <a href="{{ route('workspaces.calendar.index', $workspace) }}" style="color: var(--color-primary);">Lịch</a>
        <span style="margin: 0 0.375rem;">/</span>
        <span>Chi tiết sự kiện</span>
    </div>

    <section class="card" data-page="calendar-show" data-event-id="{{ $event->id }}" style="padding: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.25rem;">
            <div>
                <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem;">
                    <span class="badge" style="background: rgba(99, 102, 241, 0.12); color: var(--color-primary); font-weight: 600; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: var(--radius-sm);">
                        {{ \App\Support\UiLabels::text($event->event_type->value) }}
                    </span>
                    @if($event->status->value === 'completed')
                        <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #059669; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: var(--radius-sm);">
                            Đã hoàn tất
                        </span>
                    @elseif($event->status->value === 'cancelled')
                        <span class="badge" style="background: rgba(239, 68, 68, 0.12); color: #dc2626; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: var(--radius-sm);">
                            Đã hủy
                        </span>
                    @else
                        <span class="badge" style="background: rgba(100, 116, 139, 0.12); color: var(--color-text-muted); font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: var(--radius-sm);">
                            Dự kiến
                        </span>
                    @endif
                    <span class="badge" style="background: var(--color-surface-hover); border: 1px solid var(--color-border); font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: var(--radius-sm); color: var(--color-text-muted);">
                        {{ $event->visibility->value === 'private' ? 'Riêng tư' : ($event->visibility->value === 'project' ? 'Project' : 'Workspace') }}
                    </span>
                    @if($event->is_busy)
                        <span class="badge" style="background: rgba(239, 68, 68, 0.08); color: #dc2626; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: var(--radius-sm);">
                            Chiếm giờ (Bận)
                        </span>
                    @endif
                </div>
                <h1 style="font-size: 1.625rem; font-weight: 700; margin: 0; color: var(--color-text); letter-spacing: -0.01em;">
                    {{ $event->title }}
                </h1>
            </div>
        </div>

        <div style="background: var(--color-surface-hover); border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem; border: 1px solid var(--color-border);">
            <div style="display: flex; align-items: center; gap: 0.75rem; color: var(--color-text);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--color-primary); flex-shrink: 0;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <div style="font-size: 0.9375rem; font-weight: 600;">
                    {{ $event->starts_at->setTimezone($timezone)->format('H:i - d/m/Y') }} → {{ $event->ends_at->setTimezone($timezone)->format('H:i - d/m/Y') }}
                    <span style="font-size: 0.8125rem; font-weight: 400; color: var(--color-text-muted); margin-left: 0.375rem;">({{ $timezone }})</span>
                </div>
            </div>

            @if($event->all_day)
                <div style="font-size: 0.8125rem; color: var(--color-text-muted); padding-left: 2rem;">
                    📅 Sự kiện cả ngày: {{ $event->starts_at->setTimezone($event->timezone)->format('Y-m-d') }} → {{ $event->ends_at->setTimezone($event->timezone)->format('Y-m-d') }}
                </div>
            @endif

            @if($event->task)
                <div style="display: flex; align-items: center; gap: 0.75rem; color: var(--color-text); border-top: 1px solid var(--color-border); padding-top: 0.75rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #10b981; flex-shrink: 0;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    <div>
                        <span style="font-size: 0.8125rem; color: var(--color-text-muted);">Công việc liên kết:</span>
                        <a href="{{ route($event->project_id ? 'workspaces.projects.tasks.show' : 'workspaces.tasks.show', $event->project_id ? [$workspace, $event->project, $event->task] : [$workspace, $event->task]) }}" style="font-weight: 600; color: var(--color-primary); margin-left: 0.375rem;">
                            {{ $event->task->code }} — {{ $event->task->title }}
                        </a>
                    </div>
                </div>
            @endif
        </div>

        @if($event->description)
            <div style="margin-bottom: 1.5rem;">
                <h3 style="font-size: 0.875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); margin-bottom: 0.5rem;">
                    Mô tả / Chi tiết
                </h3>
                <div style="white-space: pre-wrap; font-size: 0.9375rem; line-height: 1.6; color: var(--color-text); background: var(--color-surface); padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border);">{{ $event->description }}</div>
            </div>
        @endif

        <div style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: 1.5rem; line-height: 1.5;">
            👥 Người tham gia tham khảo: <strong>{{ count($event->participants ?? []) }}</strong> người.
            <br>
            <span style="font-style: italic;">Lưu ý: Thay đổi trạng thái lịch sự kiện không làm thay đổi trạng thái công việc (task) hoặc giờ làm việc thực tế.</span>
        </div>

        <div class="actions" style="display: flex; gap: 0.75rem; align-items: center; justify-content: space-between; border-top: 1px solid var(--color-border); padding-top: 1.25rem;">
            <div style="display: flex; gap: 0.5rem;">
                @can('update', $event)
                    <a class="button" href="{{ route('workspaces.calendar.edit', [$workspace, $event]) }}" style="display: inline-flex; align-items: center; gap: 0.375rem;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Sửa sự kiện
                    </a>
                @endcan
                <a class="button secondary" href="{{ route('workspaces.calendar.index', $workspace) }}">
                    Về lịch
                </a>
            </div>

            @can('delete', $event)
                <form method="POST" action="{{ route('workspaces.calendar.destroy', [$workspace, $event]) }}" data-form="calendar-delete" data-confirm="Xóa sự kiện này?" style="margin: 0;">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="version" value="{{ $version }}">
                    <button type="submit" class="button danger" style="padding: 0.5rem 0.875rem; font-size: 0.8125rem;">
                        Xóa sự kiện
                    </button>
                </form>
            @endcan
        </div>
    </section>
</div>
</x-layout>
