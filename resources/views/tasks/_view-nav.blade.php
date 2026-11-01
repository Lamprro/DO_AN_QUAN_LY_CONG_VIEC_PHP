@php($taskView = $taskView ?? ($filters['view'] ?? 'list'))
<nav class="view-switcher jira-view-switcher" aria-label="Cách xem công việc">
    <a href="{{ route($routePrefix.'.index', array_merge($routeParams, $filters ?? [], ['view' => 'list'])) }}" @if($taskView==='list') aria-current="page" class="active" @endif>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 5px;"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>Danh sách
    </a>
    <a href="{{ route($routePrefix.'.index', array_merge($routeParams, $filters ?? [], ['view' => 'board'])) }}" @if($taskView==='board') aria-current="page" class="active" @endif>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 5px;"><rect x="3" y="3" width="5" height="18" rx="1"/><rect x="10" y="3" width="5" height="12" rx="1"/><rect x="17" y="3" width="5" height="15" rx="1"/></svg>Bảng Kanban
    </a>
    <a href="{{ route($routePrefix.'.tree', $routeParams) }}" @if($taskView==='tree') aria-current="page" class="active" @endif>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 5px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Cây phân rã (WBS)
    </a>
</nav>
<p class="view-help muted">Danh sách và Kanban giữ bộ lọc khi chuyển cách xem. Cây hiển thị toàn bộ cấu trúc phân rã công việc.</p>
