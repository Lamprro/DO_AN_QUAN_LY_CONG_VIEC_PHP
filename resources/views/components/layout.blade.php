@props(['title'=>'Quản lý công việc'])
<?php $versionedAsset = fn (string $path) => asset($path).'?v='.(is_file(public_path($path)) ? filemtime(public_path($path)) : 'missing'); ?>
<!doctype html><html lang="{{ $preferences['language']??'vi' }}" data-theme="{{ $preferences['theme']??'system' }}" data-theme-preference="{{ $preferences['theme']??'system' }}" data-theme-owner="{{ auth()->id()??'guest' }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ $title }} · Work Management</title><link rel="stylesheet" href="{{ $versionedAsset('css/app-shell.css') }}"><link rel="stylesheet" href="{{ $versionedAsset('css/p0-ui.css') }}"><link rel="stylesheet" href="{{ $versionedAsset('css/p1-observation.css') }}"><link rel="stylesheet" href="{{ $versionedAsset('css/modern-theme.css') }}"><link rel="stylesheet" href="{{ $versionedAsset('css/review-polish.css') }}"><link rel="stylesheet" href="{{ $versionedAsset('css/workflow-experience.css') }}"><link rel="stylesheet" href="{{ $versionedAsset('css/page-editor.css') }}">@stack('styles')
<style>
/* ===== CẢI TIẾN CỬA SỔ THÔNG BÁO (TASK I-002) ===== */
/* 1. Nút Xem thêm: Tối giản, dạng chữ thuần màu xám nhạt, giữ icon tam giác */
.notification-popover .btn-toggle-expand {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    padding: 2px 0 !important;
    margin-top: 4px;
    color: var(--text-muted, #94a3b8) !important;
    font-size: 12.5px !important;
    font-weight: 500 !important;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    cursor: pointer;
    transition: color 0.2s ease, opacity 0.2s ease;
    opacity: 0.85;
}
.notification-popover .btn-toggle-expand:hover {
    color: var(--text, #64748b) !important;
    opacity: 1;
    background: transparent !important;
    transform: none !important;
}

/* 2. Nút Đánh dấu: Bổ sung padding trái & phải để chữ không sát viền */
.notification-popover .btn-mark-all-read {
    padding: 6px 16px !important;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    white-space: nowrap;
}
.notification-popover .btn-toggle-read {
    padding: 4px 12px !important;
    border-radius: 6px;
}

/* 3. Nút Xem tất cả: Hiệu ứng nâng nhẹ (subtle lift) khi hover, giữ nguyên màu chữ */
.notification-popover-footer .view-all-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    width: 100%;
    padding: 8px 16px;
    border-radius: 8px;
    color: var(--primary, #4f46e5) !important;
    font-weight: 600;
    text-decoration: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}
.notification-popover-footer .view-all-link:hover {
    color: var(--primary, #4f46e5) !important;
    background-color: var(--bg-subtle, #f1f5f9) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
}
</style>
<script src="{{ $versionedAsset('js/theme.js') }}"></script><script src="{{ $versionedAsset('js/app-ui.js') }}" defer></script><script src="{{ $versionedAsset('js/page-editor.js') }}" defer></script><script src="{{ $versionedAsset('js/date-inputs.js') }}" defer></script></head>
<body><a href="#main-content" class="skip-link">Tới nội dung chính</a><header class="app-header"><a href="{{ route('home') }}" class="brand">Work Management</a>
@auth
<button type="button" class="secondary nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="app-sidebar">Menu</button>
<details class="workspace-switcher"><summary>{{ $shell['workspace']?->name??'Chọn workspace' }}</summary><nav aria-label="Chuyển workspace">
@forelse($shell['workspaces'] as $item)<a href="{{ route('workspaces.show',$item) }}">{{ $item->name }} <small>{{ \App\Support\UiLabels::text($item->kind) }}</small></a>
@empty<p>Chưa có workspace truy cập được.</p>
@endforelse<a href="{{ route('workspaces.index') }}">Tất cả workspace</a></nav></details>
<?php $quickCreateWorkspace = $shell['workspace'] ?? $shell['workspaces']->first(); ?>
@if($quickCreateWorkspace)
<a href="{{ ($shell['project'] && $quickCreateWorkspace->id === $shell['workspace']?->id) ? route('workspaces.projects.tasks.create', [$quickCreateWorkspace, $shell['project']]) : route('workspaces.tasks.create', $quickCreateWorkspace) }}" class="jira-quick-create-btn" data-task-panel title="Tạo công việc mới (Phím tắt: c)">+ Tạo việc</a>
@endif
<form method="get" action="{{ route('search.index') }}" class="global-search" role="search"><label class="sr-only" for="global-query">Tìm công việc và dự án</label><div class="search-input-wrap"><input type="search" id="global-query" name="q" maxlength="200" required placeholder="Tìm công việc, dự án…"><kbd class="global-search-kbd" title="Nhấn / hoặc Ctrl+K để tìm">/</kbd></div><button class="secondary">Tìm</button></form>
<nav aria-label="Tài khoản" class="account-nav">
<button type="button" class="theme-toggle" id="theme-toggle" aria-label="Chuyển chế độ sáng/tối" title="Chuyển chế độ sáng/tối"><span class="theme-icon light-icon" aria-hidden="true">☀️</span><span class="theme-icon dark-icon" aria-hidden="true">🌙</span></button>

{{-- Nút Chuông Thông Báo & Popover Chuẩn Mẫu --}}
<details class="notification-dropdown" id="notification-dropdown">
    <summary class="notification-bell-btn" aria-label="Xem thông báo" title="Thông báo">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>
        <span class="notification-dot-badge" data-unread-badge style="{{ ($unreadNotifications ?? 0) > 0 ? '' : 'display: none;' }}"></span>
    </summary>
    <div class="notification-popover" role="region" aria-label="Bảng thông báo nhanh">
        {{-- Header Popover --}}
        <div class="notification-popover-header">
            <div style="display: flex; align-items: center; gap: 8px;">
                <h3 style="margin: 0; font-size: 1rem; font-weight: 750; color: var(--text);">Thông báo</h3>
                <span class="badge" data-unread-count-badge style="background: var(--primary-light, #e0e7ff); color: var(--primary); font-size: 11px; font-weight: 750; padding: 2px 7px; border-radius: 10px;">
                    <span data-unread-count>{{ $unreadNotifications ?? 0 }}</span> mới
                </span>
            </div>
            <button type="button" class="btn-mark-all-read" onclick="markAllNotificationsRead(event)" title="Đánh dấu tất cả đã đọc">
                ✓ Đánh dấu tất cả đã đọc
            </button>
        </div>

        @php
            $rawRecent = $recentNotifications ?? [];
            $recentItems = method_exists($rawRecent, 'items') ? $rawRecent->items() : $rawRecent;
            $recentCollection = collect($recentItems);
            $totalNotifCount = $recentCollection->count();
            $unreadNotifCount = $recentCollection->filter(fn($n) => is_object($n) && !$n->read_at)->count();
            $taskNotifCount = $recentCollection->filter(fn($n) => is_object($n) && str_starts_with($n->type ?? '', 'task.'))->count();
        @endphp

        {{-- Tabs phân loại --}}
        <div class="notification-popover-tabs" role="tablist">
            <button type="button" class="notif-tab active" data-tab="all" onclick="filterPopoverNotifs(this, 'all')">
                Tất cả <span class="notif-tab-badge" data-badge-for="all">{{ $totalNotifCount }}</span>
            </button>
            <button type="button" class="notif-tab" data-tab="unread" onclick="filterPopoverNotifs(this, 'unread')">
                Chưa đọc <span class="notif-tab-badge" data-badge-for="unread">{{ $unreadNotifCount }}</span>
            </button>
            <button type="button" class="notif-tab" data-tab="task" onclick="filterPopoverNotifs(this, 'task')">
                Công việc <span class="notif-tab-badge" data-badge-for="task">{{ $taskNotifCount }}</span>
            </button>
        </div>

        {{-- Danh sách thông báo có cuộn --}}
        <div class="notification-popover-list" id="notification-popover-list">
            <div class="popover-notif-filter-empty" id="popover-notif-filter-empty" style="display: none; padding: 2.25rem 1rem; text-align: center; color: var(--text-muted);">
                <div style="font-size: 1.6rem; margin-bottom: 6px;">✨</div>
                <p class="filter-empty-msg" style="margin: 0; font-size: 0.85rem; font-weight: 600; color: var(--text-heading, #1e293b);"></p>
                <p style="margin: 4px 0 0; font-size: 0.75rem; color: var(--text-muted);">Bạn có thể xem lại toàn bộ thông báo trong trang Quản lý thông báo</p>
            </div>
            @forelse($recentNotifications ?? [] as $n)
                @php
                    $isUnread = !$n->read_at;
                    $isTask = str_starts_with($n->type, 'task.');
                    $taskUrl = null;
                    if ($n->task) {
                        $taskUrl = route($n->project_id ? 'workspaces.projects.tasks.show' : 'workspaces.tasks.show', $n->project_id ? [$n->workspace, $n->project, $n->task] : [$n->workspace, $n->task]);
                    } elseif (str_starts_with($n->type, 'calendar.') && $n->workspace) {
                        $taskUrl = route('workspaces.calendar.team', [$n->workspace, 'project_id' => $n->project_id]);
                    }
                    $icon = match(true) {
                        str_contains($n->type, 'comment') => '💬',
                        str_contains($n->type, 'status') => '🔄',
                        str_contains($n->type, 'created') => '📋',
                        str_contains($n->type, 'reminder') => '⏰',
                        str_contains($n->type, 'calendar') => '🤝',
                        str_contains($n->type, 'github') => '🐙',
                        default => '🔔',
                    };
                    $hasLongBody = mb_strlen($n->body) > 65;
                @endphp
                <div class="popover-notif-item {{ $isUnread ? 'is-unread' : 'is-read' }}" 
                     data-notif-id="{{ $n->id }}"
                     data-is-task="{{ $isTask ? '1' : '0' }}"
                     data-is-unread="{{ $isUnread ? '1' : '0' }}">
                    <div class="popover-notif-icon">
                        {{ $icon }}
                    </div>
                    <div class="popover-notif-content">
                        <div class="popover-notif-top">
                            <span class="popover-notif-title">{{ $n->title }}</span>
                            <span class="popover-notif-time">{{ $n->created_at->setTimezone(auth()->user()->timezone)->format('d/m · H:i') }}</span>
                        </div>
                        <div class="popover-notif-body">
                            @if($hasLongBody)
                                <div class="text-preview">{{ Str::limit($n->body, 65) }}</div>
                                <div class="text-full" hidden>{{ $n->body }}</div>
                                <button type="button" class="btn-toggle-expand" onclick="toggleNotificationText(this, event)">Xem thêm ▾</button>
                            @else
                                <div class="text-full">{{ $n->body }}</div>
                            @endif
                        </div>
                        <div class="popover-notif-actions">
                            @if($taskUrl)
                                <a href="{{ $taskUrl }}" class="popover-notif-link">Chi tiết ›</a>
                            @else
                                <span></span>
                            @endif
                            <button type="button" class="btn-toggle-read" onclick="toggleSingleNotifRead('{{ $n->id }}', this, event)">
                                {{ $isUnread ? 'Đánh dấu đã đọc' : 'Đánh dấu chưa đọc' }}
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div style="padding: 2.5rem 1rem; text-align: center; color: var(--text-muted);">
                    <div style="font-size: 1.75rem; margin-bottom: 4px;">🔔</div>
                    <p style="margin: 0; font-size: 0.85rem; font-weight: 600;">Không có thông báo mới nào</p>
                </div>
            @endforelse
        </div>

        {{-- Nút Xem tất cả (View all) dẫn sang trang Notification --}}
        <div class="notification-popover-footer">
            <a href="{{ route('notifications.index') }}" class="view-all-link">
                <span>Xem tất cả thông báo (View all)</span>
                <span style="font-size: 13px;">›</span>
            </a>
        </div>
    </div>
</details>

<details><summary data-account-name>{{ auth()->user()->name }}</summary><div class="account-menu"><a href="{{ route('profile.edit') }}">Hồ sơ</a><a href="{{ route('settings.edit') }}">Cấu hình</a><form action="{{ route('logout') }}" method="post" data-form="logout">
@csrf<button class="secondary">Đăng xuất</button></form></div></details></nav>
@else
<nav aria-label="Tài khoản" class="account-nav"><button type="button" class="theme-toggle" id="theme-toggle-guest" aria-label="Chuyển chế độ sáng/tối" title="Chuyển chế độ sáng/tối"><span class="theme-icon light-icon" aria-hidden="true">☀️</span><span class="theme-icon dark-icon" aria-hidden="true">🌙</span></button><a href="{{ route('login') }}">Đăng nhập</a><a href="{{ route('register') }}">Đăng ký</a></nav>
@endauth
</header>
@auth
@if($shell['timer'])
<section class="global-timer" aria-label="Giờ làm đang chạy" data-global-timer><div><strong>Đang ghi giờ</strong> <output data-elapsed-start="{{ $shell['timer']->started_at->toIso8601String() }}">Đang tính…</output>
@if($shell['timerTaskVisible'])<a href="{{ $shell['timer']->task->project_id?route('workspaces.projects.tasks.show',[$shell['timer']->task->workspace_id,$shell['timer']->task->project_id,$shell['timer']->task_id]):route('workspaces.tasks.show',[$shell['timer']->task->workspace_id,$shell['timer']->task_id]) }}">{{ $shell['timer']->task->code }} — {{ $shell['timer']->task->title }}</a>
@else<span>Công việc không còn quyền xem</span>
@endif</div><form method="post" action="{{ route('timers.stop',$shell['timer']) }}" data-form="global-timer-stop">
@csrf<button>Dừng timer</button></form></section>
@endif
@endauth
<div class="app-frame {{ auth()->check()?'':'guest-frame' }}">
@auth
<button type="button" class="nav-backdrop" aria-label="Đóng menu" hidden></button><aside id="app-sidebar" class="app-sidebar" aria-label="Điều hướng ứng dụng">
<button type="button" class="secondary nav-close" data-close-nav>Đóng điều hướng</button>
<div class="sidebar-header-box">
    <div class="nav-context sidebar-workspace-pill">
        <div class="sidebar-workspace-avatar" aria-hidden="true">
            {{ $shell['project'] ? '📁' : ($shell['workspace']?->kind->value === 'team' ? '🏢' : '👤') }}
        </div>
        <div class="sidebar-workspace-meta">
            <span class="eyebrow sidebar-workspace-badge">
                {{ $shell['project'] ? 'Dự án' : ($shell['workspace']?->kind->value === 'team' ? 'Team Workspace' : 'Cá nhân') }}
            </span>
            <strong class="sidebar-workspace-title" title="{{ $shell['project']?->name ?? $shell['workspace']?->name ?? 'Công việc' }}">
                {{ $shell['project']?->name ?? $shell['workspace']?->name ?? 'Công việc của bạn' }}
            </strong>
        </div>
    </div>
    <button type="button" class="sidebar-collapse-toggle" id="sidebar-collapse-btn" title="Thu gọn / Mở rộng thanh điều hướng" aria-label="Thu gọn hoặc mở rộng thanh điều hướng">
        <svg class="collapse-icon-svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
            <path d="M9 3v18"></path>
            <path d="M14 9l-3 3 3 3"></path>
        </svg>
    </button>
</div>
@foreach(collect($shell['menu'])->groupBy('group')->sortBy(fn($items,$group)=>array_search($group,['Dự án hiện tại','Công việc cá nhân','Không gian làm việc','Cá nhân'])) as $group=>$items)
<nav class="nav-group" aria-label="{{ $group }}"><h2>{{ $group }}</h2>
@foreach($items as $item)
<?php
$icon = match(true) {
    str_contains($item['label'], 'Bảng') || str_contains($item['url'], 'board') => '📊',
    str_contains($item['label'], 'Cây') || str_contains($item['url'], 'tree') => '🌲',
    str_contains($item['label'], 'Công việc') || str_contains($item['label'], 'việc') => '📋',
    str_contains($item['label'], 'Dự án') || str_contains($item['label'], 'Projects') => '📁',
    str_contains($item['label'], 'Kế hoạch') => '📑',
    str_contains($item['label'], 'Timeline') => '📊',
    str_contains($item['label'], 'Lịch') => '📅',
    str_contains($item['label'], 'Dashboard') || str_contains($item['label'], 'báo cáo') => '📈',
    str_contains($item['label'], 'Thành viên') => '👥',
    str_contains($item['label'], 'Vai trò') => '🛡️',
    str_contains($item['label'], 'Tổng quan') => '🏠',
    str_contains($item['label'], 'Giờ') || str_contains($item['label'], 'timer') => '⏱️',
    str_contains($item['label'], 'Thông báo') => '🔔',
    str_contains($item['label'], 'GitHub') => '🐙',
    str_contains($item['label'], 'Mẫu') => '📝',
    str_contains($item['label'], 'Vận hành') => '⚡',
    default => '🔹',
};
?>
<a href="{{ $item['url'] }}" @if($item['active']) aria-current="page" @endif><span class="nav-item-icon" aria-hidden="true">{{ $icon }}</span><span>{{ $item['label'] }}</span></a>
@endforeach
</nav>
@endforeach
</aside>
@endauth
<main id="main-content" tabindex="-1">
@if(!empty($shell['breadcrumbs']))<nav class="breadcrumbs" aria-label="Đường dẫn">
@foreach($shell['breadcrumbs'] as $crumb)
@if(!$loop->first)<span aria-hidden="true">/</span>
@endif
@if($crumb['url'])<a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
@else<span aria-current="page">{{ $crumb['label'] }}</span>
@endif
@endforeach</nav>
@endif
{{-- Modern Toast Notifications Carrier for Flash messages & Validation summaries --}}
<div class="flash-messages-carrier" hidden style="display: none;">
@foreach(['success', 'warning', 'error', 'info', 'status'] as $flash)
@if(session($flash))
@php
    $normalizedType = match($flash) {
        'error' => 'error',
        'warning' => 'warning',
        'info' => 'info',
        default => 'success',
    };
@endphp
<span class="flash-toast-item" data-flash-type="{{ $normalizedType }}" data-flash-message="{{ session($flash) }}"></span>
@endif
@endforeach
@if(isset($errors) && $errors->any())
@php
    $isRegisterPage = request()->routeIs('register*') || request()->is('register*');
    $isLoginPage = request()->routeIs('login*') || request()->is('login*');
    if ($isRegisterPage) {
        $errorToastMsg = 'Đăng ký không thành công. Vui lòng kiểm tra lại các trường thông tin bên dưới.';
    } elseif ($isLoginPage) {
        $errorToastMsg = 'Đăng nhập không thành công. Vui lòng kiểm tra lại thông tin tài khoản.';
    } else {
        $errorToastMsg = 'Dữ liệu nhập chưa hợp lệ. Vui lòng kiểm tra lại các trường có báo lỗi.';
    }
@endphp
<span class="flash-toast-item" data-flash-type="error" data-flash-message="{{ $errorToastMsg }}"></span>
@endif
</div>

{{-- Screen-reader accessible error summary without rendering a disruptive visual card --}}
@if(isset($errors) && $errors->any())
<section class="sr-only" role="alert" tabindex="-1" id="error-summary">
<h2>Vui lòng kiểm tra lại</h2>
<p>Dữ liệu nhập được giữ lại. Chọn một lỗi để đến trường cần sửa.</p>
<ul>
@foreach($errors->getMessages() as $field=>$messages)
@foreach($messages as $message)<li><a href="#main-content" data-error-key="{{ $field }}">{{ $message }}</a></li>
@endforeach
@endforeach
</ul>
</section>
@endif

{{-- No-script fallback for users without JavaScript --}}
<noscript>
@foreach(['success', 'warning', 'error', 'info', 'status'] as $flash)
@if(session($flash))
<div class="notice {{ in_array($flash, ['error', 'warning', 'info', 'success']) ? $flash : 'success' }}">{{ session($flash) }}</div>
@endif
@endforeach
@if(isset($errors) && $errors->any())
<section class="notice errors" role="alert">
<h2>Vui lòng kiểm tra lại</h2>
<p>Vui lòng xem lỗi tại từng trường bên dưới.</p>
</section>
@endif
</noscript>
{{ $slot }}<div id="toast-container" class="toast-container" aria-live="polite" aria-atomic="true"></div><footer>Quản lý công việc cá nhân và nhóm.</footer></main></div>@auth
<dialog id="page-editor" class="page-editor" aria-labelledby="page-editor-title">
    <header class="editor-toolbar">
        <div class="editor-heading"><h2 id="page-editor-title">Chỉnh sửa</h2><p data-editor-status role="status"></p></div>
        <a data-editor-standalone target="_blank" rel="noopener">Mở trang riêng</a>
        <button type="button" data-editor-save hidden>Lưu thay đổi</button>
        <button type="button" class="secondary" data-editor-close aria-label="Đóng cửa sổ">×</button>
    </header>
    <div data-editor-frames></div>
</dialog>
@endauth
@stack('scripts')
<script>
(function() {
    try {
        var frame = document.querySelector('.app-frame');
        if (frame && localStorage.getItem('sidebar_collapsed') === 'true') {
            frame.classList.add('sidebar-collapsed');
        }
    } catch(e) {}

    var btn = document.getElementById('sidebar-collapse-btn');
    if (btn) {
        btn.addEventListener('click', function() {
            var frame = document.querySelector('.app-frame');
            if (frame) {
                var isCollapsed = frame.classList.toggle('sidebar-collapsed');
                try {
                    localStorage.setItem('sidebar_collapsed', isCollapsed ? 'true' : 'false');
                } catch(e) {}
            }
        });
    }
})();

// Xử lý Popover Thông báo & Mở rộng/Thu gọn văn bản dài
function toggleNotificationText(btn, event) {
    if (event) event.stopPropagation();
    var container = btn.closest('.popover-notif-body, .notification-full-body');
    if (!container) return;
    var preview = container.querySelector('.text-preview, .notif-body-preview');
    var full = container.querySelector('.text-full, .notif-body-full');
    if (!preview || !full) return;

    if (full.hasAttribute('hidden')) {
        full.removeAttribute('hidden');
        preview.setAttribute('hidden', 'true');
        btn.textContent = 'Thu gọn ▴';
    } else {
        full.setAttribute('hidden', 'true');
        preview.removeAttribute('hidden');
        btn.textContent = 'Xem thêm ▾';
    }
}

function filterPopoverNotifs(tabBtn, filter) {
    var popover = tabBtn.closest('.notification-popover');
    if (!popover) return;
    popover.querySelectorAll('.notif-tab').forEach(function(b) { b.classList.remove('active'); });
    tabBtn.classList.add('active');

    var items = popover.querySelectorAll('.popover-notif-item');
    var visibleCount = 0;
    items.forEach(function(item) {
        var show = false;
        if (filter === 'all') {
            show = true;
        } else if (filter === 'unread') {
            show = item.dataset.isUnread === '1';
        } else if (filter === 'task') {
            show = item.dataset.isTask === '1';
        }
        item.style.display = show ? 'flex' : 'none';
        if (show) visibleCount++;
    });

    var filterEmpty = popover.querySelector('#popover-notif-filter-empty');
    if (filterEmpty) {
        if (items.length > 0 && visibleCount === 0) {
            var msgEl = filterEmpty.querySelector('.filter-empty-msg');
            if (msgEl) {
                if (filter === 'unread') {
                    msgEl.textContent = 'Tuyệt vời! Bạn không còn thông báo chưa đọc nào.';
                } else if (filter === 'task') {
                    msgEl.textContent = 'Không có thông báo nào thuộc loại Công việc.';
                } else {
                    msgEl.textContent = 'Không có thông báo nào phù hợp bộ lọc.';
                }
            }
            filterEmpty.style.display = 'block';
        } else {
            filterEmpty.style.display = 'none';
        }
    }
}

function updatePopoverTabCounts(popover) {
    if (!popover) popover = document.querySelector('.notification-popover');
    if (!popover) return;
    var items = popover.querySelectorAll('.popover-notif-item');
    var total = items.length;
    var unread = 0;
    var task = 0;
    items.forEach(function(it) {
        if (it.dataset.isUnread === '1') unread++;
        if (it.dataset.isTask === '1') task++;
    });
    var bAll = popover.querySelector('[data-badge-for="all"]');
    var bUnread = popover.querySelector('[data-badge-for="unread"]');
    var bTask = popover.querySelector('[data-badge-for="task"]');
    if (bAll) bAll.textContent = String(total);
    if (bUnread) bUnread.textContent = String(unread);
    if (bTask) bTask.textContent = String(task);

    var activeTab = popover.querySelector('.notif-tab.active');
    if (activeTab) {
        var filter = activeTab.dataset.tab || 'all';
        var visibleCount = 0;
        items.forEach(function(item) {
            var show = false;
            if (filter === 'all') {
                show = true;
            } else if (filter === 'unread') {
                show = item.dataset.isUnread === '1';
            } else if (filter === 'task') {
                show = item.dataset.isTask === '1';
            }
            item.style.display = show ? 'flex' : 'none';
            if (show) visibleCount++;
        });

        var filterEmpty = popover.querySelector('#popover-notif-filter-empty');
        if (filterEmpty) {
            if (items.length > 0 && visibleCount === 0) {
                var msgEl = filterEmpty.querySelector('.filter-empty-msg');
                if (msgEl) {
                    if (filter === 'unread') {
                        msgEl.textContent = 'Tuyệt vời! Bạn không còn thông báo chưa đọc nào.';
                    } else if (filter === 'task') {
                        msgEl.textContent = 'Không có thông báo nào thuộc loại Công việc.';
                    } else {
                        msgEl.textContent = 'Không có thông báo nào phù hợp bộ lọc.';
                    }
                }
                filterEmpty.style.display = 'block';
            } else {
                filterEmpty.style.display = 'none';
            }
        }
    }
}

async function markAllNotificationsRead(event) {
    if (event) event.stopPropagation();
    var token = document.querySelector('meta[name="csrf-token"]')?.content;
    try {
        var res = await fetch('{{ route("notifications.read-all") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        var data = await res.json();
        if (data.success) {
            document.querySelectorAll('[data-unread-count]').forEach(function(el) { el.textContent = '0'; });
            document.querySelectorAll('[data-unread-badge]').forEach(function(el) { el.style.display = 'none'; });
            document.querySelectorAll('.popover-notif-item').forEach(function(item) {
                item.classList.remove('is-unread');
                item.classList.add('is-read');
                item.dataset.isUnread = '0';
                var readBtn = item.querySelector('.btn-toggle-read');
                if (readBtn) readBtn.textContent = 'Đánh dấu chưa đọc';
            });
            updatePopoverTabCounts(document.querySelector('.notification-popover'));
            if (window.showToast) window.showToast('Đã đánh dấu tất cả thông báo là đã đọc.', 'success');
        }
    } catch (e) {
        console.error(e);
    }
}

async function toggleSingleNotifRead(id, btn, event) {
    if (event) event.stopPropagation();
    var item = btn.closest('.popover-notif-item');
    if (!item) return;
    var isCurrentlyUnread = item.dataset.isUnread === '1';
    var token = document.querySelector('meta[name="csrf-token"]')?.content;

    try {
        var res = await fetch('/notifications/' + id + '/read', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ unread: !isCurrentlyUnread })
        });
        var data = await res.json();
        if (data.success) {
            var nowRead = data.read;
            item.classList.toggle('is-unread', !nowRead);
            item.classList.toggle('is-read', nowRead);
            item.dataset.isUnread = nowRead ? '0' : '1';
            btn.textContent = nowRead ? 'Đánh dấu chưa đọc' : 'Đánh dấu đã đọc';

            var count = data.unread_count ?? 0;
            document.querySelectorAll('[data-unread-count]').forEach(function(el) { el.textContent = String(count); });
            document.querySelectorAll('[data-unread-badge]').forEach(function(el) {
                el.style.display = count > 0 ? '' : 'none';
            });
            updatePopoverTabCounts(document.querySelector('.notification-popover'));
        }
    } catch (e) {
        console.error(e);
    }
}

document.addEventListener('click', function(e) {
    var dropdown = document.getElementById('notification-dropdown');
    if (dropdown && dropdown.hasAttribute('open') && !dropdown.contains(e.target)) {
        dropdown.removeAttribute('open');
    }
});
</script>
</body></html>