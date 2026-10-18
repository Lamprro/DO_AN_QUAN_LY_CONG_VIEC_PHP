<x-layout title="Thông báo"><section class="card" data-page="notification-inbox"><h1>Thông báo</h1><form method="get" class="filter-bar" data-form="notification-filter"><label for="notification-filter">Trạng thái</label><select id="notification-filter" name="filter">
@foreach(['all'=>'Tất cả','unread'=>'Chưa đọc','read'=>'Đã đọc'] as $key=>$label)<option value="{{ $key }}"
@selected(request('filter','all')===$key)>{{ $label }}</option>
@endforeach</select><label for="notification-type">Loại</label><select id="notification-type" name="type"><option value="">Tất cả</option>
@foreach(['task.created'=>'Giao việc mới','task.updated'=>'Thay đổi phân công','task.status_changed'=>'Thay đổi trạng thái / review','task.reminder_due'=>'Nhắc hạn','task.reminder_review'=>'Nhắc duyệt','task.comment_added'=>'Bình luận'] as $key=>$label)<option value="{{ $key }}"
@selected(request('type')===$key)>{{ $label }}</option>
@endforeach</select><button>Lọc</button></form><p>Chỉ thông báo của bạn và còn quyền xem nguồn. Trạng thái đọc chỉ thay đổi sau khi lưu thành công.</p><form method="POST" action="{{ route('notifications.read-all') }}" data-form="notification-read-all">
@csrf<button type="submit">Đánh dấu tất cả được phép xem đã đọc</button></form></section>
@forelse($notifications as $notification)<article class="card notification-item {{ $notification->read_at?'':'is-unread' }}" data-notification-id="{{ $notification->id }}"><span class="badge">{{ $notification->read_at?'Đã đọc':'Chưa đọc' }}</span><h2>{{ $notification->title }}</h2><p>{{ $notification->body }}</p><p>{{ ['task.created'=>'Giao việc','task.updated'=>'Phân công','task.status_changed'=>'Trạng thái / review','task.reminder_due'=>'Nhắc hạn','task.reminder_review'=>'Nhắc duyệt','task.comment_added'=>'Bình luận'][$notification->type]??'Thông báo hệ thống' }} · {{ $notification->created_at->setTimezone(auth()->user()->timezone)->format('d/m/Y H:i') }} · {{ auth()->user()->timezone }}</p>
@if($notification->task)<a href="{{ route($notification->project_id?'workspaces.projects.tasks.show':'workspaces.tasks.show',$notification->project_id?[$notification->workspace,$notification->project,$notification->task]:[$notification->workspace,$notification->task]) }}">Mở công việc</a>
@endif
<form method="POST" action="{{ route('notifications.read',$notification->id) }}" data-form="notification-read">
@csrf
@method('PATCH')<input type="hidden" name="unread" value="{{ $notification->read_at?1:0 }}"><button type="submit" class="secondary">{{ $notification->read_at?'Đánh dấu chưa đọc':'Đánh dấu đã đọc' }}</button></form></article>
@empty<section class="card">Không có thông báo phù hợp bộ lọc và quyền hiện tại. <a href="{{ route('notifications.index') }}">Xóa bộ lọc</a></section>
@endforelse{{ $notifications->links() }}</x-layout>
