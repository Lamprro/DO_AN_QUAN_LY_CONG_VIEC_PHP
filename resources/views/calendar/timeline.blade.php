<x-layout title="Timeline project — {{ $project->name }}">
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
    <div>
        <div class="breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: 0.375rem;">
            <a href="{{ route('workspaces.show', $workspace) }}" style="color: var(--color-primary); font-weight: 500;">{{ $workspace->name }}</a>
            <span style="margin: 0 0.375rem;">/</span>
            <a href="{{ route('workspaces.projects.show', [$workspace, $project]) }}" style="color: var(--color-primary); font-weight: 500;">{{ $project->name }}</a>
            <span style="margin: 0 0.375rem;">/</span>
            <span>Timeline</span>
        </div>
        <h1 style="font-size: 1.625rem; font-weight: 700; margin: 0; color: var(--color-text); letter-spacing: -0.02em;">
            Dòng thời gian (Timeline) — {{ $project->name }}
        </h1>
        <p style="margin: 0.25rem 0 0; font-size: 0.875rem; color: var(--color-text-muted);">
            Trực quan hóa các khối thời gian TASK_BLOCK dự kiến trên trục thời gian ngang
        </p>
    </div>
    <div class="actions" style="display: flex; gap: 0.5rem;">
        <a class="button secondary" href="{{ route('workspaces.calendar.index', [$workspace, 'project_id' => $project->id]) }}" style="display: inline-flex; align-items: center; gap: 0.375rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Xem dạng lưới Lịch
        </a>
        <a class="button secondary" href="{{ route('workspaces.projects.show', [$workspace, $project]) }}">
            Về dự án
        </a>
    </div>
</div>

<section class="card" data-page="project-timeline" style="margin-bottom: 1.5rem; padding: 1.25rem 1.5rem;">
    @include('calendar._range')
    <div style="margin-top: 1rem; padding: 0.75rem 1rem; background: rgba(99, 102, 241, 0.05); border-left: 3px solid var(--color-primary); border-radius: var(--radius-sm); font-size: 0.8125rem; color: var(--color-text-muted);">
        💡 Thanh biểu thị là <strong>TASK_BLOCK</strong> thực tế được cắt tại biên phạm vi. Thời gian hiển thị theo <strong>{{ $range['timezone'] }}</strong> (đây là kế hoạch dự kiến, không đại diện cho giờ thực tế). Thanh có gạch chéo là sự kiện đã hủy.
    </div>
</section>

<section class="card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2 style="font-size: 1.125rem; font-weight: 700; margin: 0; color: var(--color-text); display: flex; align-items: center; gap: 0.5rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
            Biểu đồ trục thời gian (Gantt)
        </h2>
        <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
            Kéo hoặc cuộn ngang để xem toàn bộ phạm vi
        </span>
    </div>

    <div class="timeline-scroll" role="region" aria-label="Trục thời gian có thể cuộn ngang" tabindex="0">
        <div class="timeline-canvas" style="min-width: {{ max(720, count($range['days']) * 70) }}px;">
            <div class="timeline-axis">
                @foreach($navigation['ticks'] as $tick)
                    <span style="left: {{ $tick['offset'] }}%;">{{ $tick['label'] }}</span>
                @endforeach
            </div>

            @forelse($groups as $group)
                <div class="timeline-group">
                    <h2>
                        <span class="badge" style="background: rgba(99, 102, 241, 0.12); color: var(--color-primary); font-family: var(--font-mono); font-size: 0.75rem; padding: 0.125rem 0.5rem; border-radius: var(--radius-sm);">
                            {{ $group->first()['task']?->code ?? 'TASK' }}
                        </span>
                        <span>{{ $group->first()['task']?->title ?? 'Khung lịch được phép xem' }}</span>
                    </h2>
                    <div class="timeline-lanes">
                        @foreach($group as $row)
                            <div class="timeline-slot-track">
                                <a class="timeline-slot {{ $row['status'] === 'cancelled' ? 'event-cancelled' : '' }}"
                                   href="{{ route('workspaces.calendar.show', [$workspace, $row['id']]) }}"
                                   style="left: {{ $row['offset_percent'] }}%; width: {{ $row['width_percent'] }}%;"
                                   title="{{ $row['title'] }} ({{ $row['starts_at']->format('d/m H:i') }} → {{ $row['ends_at']->format('d/m H:i') }})"
                                   aria-label="{{ $row['title'].' · '.$row['starts_at']->format('d/m H:i').' → '.$row['ends_at']->format('d/m H:i').' · '.\App\Support\UiLabels::text($row['status']) }}">
                                    <span class="visually-hidden">{{ $row['title'] }}</span>
                                </a>
                            </div>
                        @endforeach

                        @if($navigation['nowPercent'] !== null)
                            <span class="timeline-now" style="left: {{ $navigation['nowPercent'] }}%;" title="Thời điểm hiện tại">
                                <span>Hiện tại</span>
                            </span>
                        @endif
                    </div>
                </div>
            @empty
                <div style="text-align: center; padding: 3rem 1rem; color: var(--color-text-muted);">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 0.5rem; opacity: 0.5;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <p style="margin: 0; font-size: 0.9375rem;">Không có TASK_BLOCK nào được phép xem trong phạm vi thời gian này.</p>
                </div>
            @endforelse
        </div>
    </div>
</section>

<section class="card" style="padding: 1.5rem;">
    <h2 style="font-size: 1.125rem; font-weight: 700; margin: 0 0 1rem; color: var(--color-text); display: flex; align-items: center; gap: 0.5rem;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        Danh sách công việc tương đương
    </h2>
    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
        @forelse($groups as $group)
            @foreach($group as $row)
                @include('calendar._row')
            @endforeach
        @empty
            <p class="muted" style="margin: 0; font-size: 0.875rem;">Chưa có khung công việc trong phạm vi.</p>
        @endforelse
    </div>
</section>
</x-layout>
