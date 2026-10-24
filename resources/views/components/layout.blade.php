
@props(['title'=>'Quản lý công việc'])
@php($versionedAsset = fn (string $path) => asset($path).'?v='.(is_file(public_path($path)) ? filemtime(public_path($path)) : 'missing'))
<!doctype html><html lang="{{ $preferences['language']??'vi' }}" data-theme="{{ $preferences['theme']??'system' }}" data-theme-preference="{{ $preferences['theme']??'system' }}" data-theme-owner="{{ auth()->id()??'guest' }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ $title }} · Work Management</title><link rel="stylesheet" href="{{ $versionedAsset('css/app-shell.css') }}"><link rel="stylesheet" href="{{ $versionedAsset('css/p0-ui.css') }}"><link rel="stylesheet" href="{{ $versionedAsset('css/p1-observation.css') }}"><link rel="stylesheet" href="{{ $versionedAsset('css/modern-theme.css') }}"><link rel="stylesheet" href="{{ $versionedAsset('css/review-polish.css') }}"><link rel="stylesheet" href="{{ $versionedAsset('css/workflow-experience.css') }}"><script src="{{ $versionedAsset('js/theme.js') }}"></script><script src="{{ $versionedAsset('js/app-ui.js') }}" defer></script></head>
<body><a href="#main-content" class="skip-link">Tới nội dung chính</a><header class="app-header"><a href="{{ route('home') }}" class="brand">Work Management</a>
@auth
<button type="button" class="secondary nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="app-sidebar">Menu</button>
<details class="workspace-switcher"><summary>{{ $shell['workspace']?->name??'Chọn workspace' }}</summary><nav aria-label="Chuyển workspace">
@forelse($shell['workspaces'] as $item)<a href="{{ route('workspaces.show',$item) }}">{{ $item->name }} <small>{{ \App\Support\UiLabels::text($item->kind) }}</small></a>
@empty<p>Chưa có workspace truy cập được.</p>
@endforelse<a href="{{ route('workspaces.index') }}">Tất cả workspace</a></nav></details>
<form method="get" action="{{ route('search.index') }}" class="global-search" role="search"><label class="sr-only" for="global-query">Tìm công việc và dự án</label><input type="search" id="global-query" name="q" maxlength="200" required placeholder="Tìm công việc, dự án…"><button class="secondary">Tìm</button></form>
<nav aria-label="Tài khoản" class="account-nav"><button type="button" class="theme-toggle" id="theme-toggle" aria-label="Chuyển chế độ sáng/tối" title="Chuyển chế độ sáng/tối"><span class="theme-icon light-icon" aria-hidden="true">☀️</span><span class="theme-icon dark-icon" aria-hidden="true">🌙</span></button><a href="{{ route('notifications.index') }}">Thông báo <span class="count" data-unread-count>{{ $unreadNotifications??0 }}</span></a><details><summary>{{ auth()->user()->name }}</summary><div class="account-menu"><a href="{{ route('profile.edit') }}">Hồ sơ</a><a href="{{ route('settings.edit') }}">Cấu hình</a><form action="{{ route('logout') }}" method="post" data-form="logout">
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
<div class="nav-context"><span class="eyebrow">{{ $shell['project']?'Dự án hiện tại':($shell['workspace']?'Không gian hiện tại':'Khu vực cá nhân') }}</span><strong>{{ $shell['project']?->name ?? $shell['workspace']?->name ?? 'Công việc của bạn' }}</strong></div>
@foreach(collect($shell['menu'])->groupBy('group')->sortBy(fn($items,$group)=>array_search($group,['Dự án hiện tại','Công việc cá nhân','Không gian làm việc','Cá nhân'])) as $group=>$items)
<nav class="nav-group" aria-label="{{ $group }}"><h2>{{ $group }}</h2>
@foreach($items as $item)<a href="{{ $item['url'] }}" @if($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>@endforeach
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
@foreach(['success','warning'] as $flash)
@if(session($flash))<div class="notice {{ $flash }}" role="status" data-flash-type="{{ $flash }}" data-flash-message="{{ session($flash) }}">{{ session($flash) }}</div>
@endif
@endforeach
@if(isset($errors)&&$errors->any())<section class="notice errors" role="alert" tabindex="-1" id="error-summary"><h2>Vui lòng kiểm tra lại</h2><p>Dữ liệu nhập được giữ lại. Chọn một lỗi để đến trường cần sửa.</p><ul>
@foreach($errors->getMessages() as $field=>$messages)
@foreach($messages as $message)<li><a href="#main-content" data-error-key="{{ $field }}">{{ $message }}</a></li>
@endforeach
@endforeach</ul></section>
@endif
{{ $slot }}<div id="toast-container" class="toast-container" aria-live="polite" aria-atomic="true"></div><footer>Quản lý công việc cá nhân và nhóm.</footer></main></div></body></html>
