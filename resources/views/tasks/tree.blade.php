<x-layout title="Cây công việc — {{ $project?->name ?? $workspace->name }}">
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
    <div>
        <div class="breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: 0.375rem;">
            <a href="{{ route('workspaces.show', $workspace) }}" style="color: var(--color-primary); font-weight: 500;">{{ $workspace->name }}</a>
            @if($project)
                <span style="margin: 0 0.375rem;">/</span>
                <a href="{{ route('workspaces.projects.show', [$workspace, $project]) }}" style="color: var(--color-primary); font-weight: 500;">{{ $project->name }}</a>
            @endif
            <span style="margin: 0 0.375rem;">/</span>
            <span>Cây công việc (WBS)</span>
        </div>
        <h1 style="font-size: 1.625rem; font-weight: 700; margin: 0; color: var(--color-text); letter-spacing: -0.02em;">
            Cây phân rã công việc (WBS)
        </h1>
        <p style="margin: 0.25rem 0 0; font-size: 0.875rem; color: var(--color-text-muted);">
            Cấu trúc phân cấp đa tầng từ Nhóm việc (Group), Công việc (Work) đến Cột mốc (Milestone)
        </p>
    </div>
    <div class="actions" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <button type="button" id="btn-tree-expand-all" class="button secondary" style="padding: 0.45rem 0.75rem; font-size: 0.8125rem;">
            ⊞ Mở rộng tất cả
        </button>
        <button type="button" id="btn-tree-collapse-all" class="button secondary" style="padding: 0.45rem 0.75rem; font-size: 0.8125rem;">
            ⊟ Thu gọn tất cả
        </button>
        <a class="button secondary" href="{{ route($routePrefix.'.index', $routeParams) }}" style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.45rem 0.75rem; font-size: 0.8125rem;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            Danh sách công việc
        </a>
    </div>
</div>

@include('tasks._view-nav',['taskView'=>'tree'])

{{-- Thống kê tiến độ WORK lá --}}
<section class="card" style="padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
        <div style="font-weight: 700; font-size: 0.9375rem; color: var(--color-text); display: flex; align-items: center; gap: 0.5rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--color-primary);"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            Tiến độ hoàn thành các công việc (WORK lá)
        </div>
        <div style="font-size: 1.125rem; font-weight: 700; color: var(--color-primary); font-family: var(--font-mono);">
            {{ $progress['percent'] }}%
        </div>
    </div>

    <div style="background: var(--color-surface-hover); height: 10px; border-radius: 9999px; overflow: hidden; border: 1px solid var(--color-border); margin-bottom: 0.5rem;">
        <div style="height: 100%; width: {{ min(100, max(0, $progress['percent'])) }}%; background: linear-gradient(90deg, var(--color-primary), #10b981); border-radius: 9999px; transition: width 0.3s ease;"></div>
    </div>

    <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; color: var(--color-text-muted);">
        <span>Đã hoàn tất: <strong>{{ $progress['done'] }}/{{ $progress['total'] }}</strong> {{ $progress['method']==='estimate' ? 'phút ước lượng' : 'công việc' }}</span>
        <span>{{ $progress['method']==='count' ? 'Tính theo số lượng (do có task chưa có ước lượng)' : 'Tính theo thời lượng ước lượng' }} (loại trừ các task đã hủy)</span>
    </div>
</section>

<section class="card" data-page="task-tree" style="padding: 1.5rem;">
    @include('tasks.tree-node', ['parentId' => 'root', 'visited' => []])
</section>
</x-layout>
