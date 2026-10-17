@props(['title' => 'Quản lý công việc'])
<!doctype html>
<html lang="{{ $preferences['language'] ?? 'vi' }}" data-theme="{{ $preferences['theme'] ?? 'system' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ $title }} · Work Management</title><link rel="stylesheet" href="{{ asset('css/app-shell.css') }}"><script src="{{ asset('js/form-confirm.js') }}" defer></script></head>
<body>
<header><strong>Work Management</strong><nav>
@auth
<a href="{{ route('workspaces.index') }}">Workspace</a><a href="{{ route('profile.edit') }}">Hồ sơ</a><a href="{{ route('settings.edit') }}">Cấu hình</a><a href="{{ route('notifications.index') }}">Thông báo <span data-unread-count>{{ $unreadNotifications ?? 0 }}</span></a><span>{{ auth()->user()->name }}</span>
<form action="{{ route('logout') }}" method="POST" data-form="logout">@csrf<button class="secondary" type="submit">Đăng xuất</button></form>
@else
<a href="{{ route('login') }}">Đăng nhập</a><a href="{{ route('register') }}">Đăng ký</a>
@endauth
<a href="{{ route('timers.index') }}">Giờ thực tế</a></nav></header>
<main>
@foreach(['success','warning'] as $flash)
@if(session($flash))<div class="notice {{ $flash }}" role="status">{{ session($flash) }}</div>@endif
@endforeach
@if(isset($errors) && $errors->any())<div class="notice errors" role="alert"><strong>Vui lòng kiểm tra lại:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
{{ $slot }}
<footer>Quản lý công việc cá nhân và nhóm.</footer>
</main></body></html>
