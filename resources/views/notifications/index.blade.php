<x-layout title="Hộp thư Thông báo">
<div class="page-heading" style="margin-bottom: 1.25rem;">
    <div>
        <p class="eyebrow">INBOX</p>
        <h1 style="margin: 0;">Thông báo của bạn</h1>
        <p class="muted" style="margin-top: 0.25rem;">Theo dõi các cập nhật phân công, trạng thái công việc và lời nhắc hạn</p>
    </div>
</div>

<section class="card" data-page="notification-inbox" style="border-top: 4px solid var(--primary); padding: 1.25rem; margin-bottom: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem;">
        <form method="get" class="filter-bar" data-form="notification-filter" style="margin: 0; flex: 1; min-width: 300px;">
            <div style="display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap;">
                <div>
                    <label for="notification-filter">Trạng thái</label>
                    <select id="notification-filter" name="filter" style="min-width: 130px;">
                        @foreach(['all'=>'Tất cả','unread'=>'Chưa đọc','read'=>'Đã đọc'] as $key=>$label)
                            <option value="{{ $key }}" @selected(request('filter','all')===$key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="notification-type">Loại thông báo</label>
                    <select id="notification-type" name="type" style="min-width: 170px;">
                        <option value="">Tất cả loại</option>
                        @foreach(['task.created'=>'Giao việc mới','task.updated'=>'Thay đổi phân công','task.status_changed'=>'Thay đổi trạng thái / review','task.reminder_due'=>'Nhắc hạn','task.reminder_review'=>'Nhắc duyệt','task.comment_added'=>'Bình luận','task.mentioned'=>'Nhắc tên','calendar.proposed'=>'Đề xuất lịch','calendar.responded'=>'Phản hồi lịch'] as $key=>$label)
                            <option value="{{ $key }}" @selected(request('type')===$key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="submit">Lọc</button>
                </div>
            </div>
        </form>

        <form method="POST" action="{{ route('notifications.read-all') }}" data-form="notification-read-all" style="margin: 0;">
            @csrf
            <button type="submit" class="secondary" style="font-size: 0.825rem;">
                ✓ Đánh dấu tất cả đã đọc
            </button>
        </form>
    </div>
</section>

<div style="display: flex; flex-direction: column; gap: 0.75rem;">
    @forelse($notifications as $notification)
        <article class="card notification-item {{ $notification->read_at?'':'is-unread' }}" data-notification-id="{{ $notification->id }}" style="margin: 0; padding: 1.25rem; border-left: 4px solid {{ $notification->read_at ? 'var(--border)' : 'var(--primary)' }}; background: {{ $notification->read_at ? 'var(--surface)' : 'var(--bg-subtle)' }} !important;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.75rem;">
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                        <span class="badge" style="{{ $notification->read_at ? '' : 'background: var(--primary); color: #fff; font-weight: 700;' }}">
                            {{ $notification->read_at ? 'Đã đọc' : 'Chưa đọc' }}
                        </span>
                        <span class="badge">
                            {{ ['task.created'=>'Giao việc','task.updated'=>'Phân công','task.status_changed'=>'Trạng thái / review','task.reminder_due'=>'Nhắc hạn','task.reminder_review'=>'Nhắc duyệt','task.comment_added'=>'Bình luận','task.mentioned'=>'Nhắc tên','calendar.proposed'=>'Đề xuất lịch','calendar.responded'=>'Phản hồi lịch'][$notification->type]??'Thông báo hệ thống' }}
                        </span>
                        <small class="muted" style="font-family: var(--font-mono); font-size: 0.75rem;">
                            {{ $notification->created_at->setTimezone(auth()->user()->timezone)->format('d/m/Y H:i') }}
                        </small>
                    </div>

                    <h2 style="font-size: 1.1rem; margin: 0.25rem 0;">{{ $notification->title }}</h2>
                    <p style="margin: 0.25rem 0 0.5rem 0; color: var(--text-muted); font-size: 0.9rem;">{{ $notification->body }}</p>

                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: 0.75rem;">
                        @if(str_starts_with($notification->type,'calendar.') && $notification->workspace)
@php(preg_match('/^calendar-proposal:([a-f0-9-]{36}):/',$notification->dedupe_key??'',$proposalMatch))
<a href="{{ route('workspaces.calendar.team',[$notification->workspace,'project_id'=>$notification->project_id,'focus'=>$proposalMatch[1]??null]) }}">Mở đề xuất lịch ›</a>@endif
                        @if($notification->task)
                            <a href="{{ route($notification->project_id?'workspaces.projects.tasks.show':'workspaces.tasks.show',$notification->project_id?[$notification->workspace,$notification->project,$notification->task]:[$notification->workspace,$notification->task]) }}" style="font-weight: 600; font-size: 0.85rem;">
                                Mở công việc liên quan ›
                            </a>
                        @endif

                        <form method="POST" action="{{ route('notifications.read',$notification->id) }}" data-form="notification-read" style="margin: 0;">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="unread" value="{{ $notification->read_at?1:0 }}">
                            <button type="submit" class="secondary" style="font-size: 0.75rem; padding: 0.2rem 0.6rem; min-height: 28px;">
                                {{ $notification->read_at ? 'Đánh dấu chưa đọc' : 'Đánh dấu đã đọc' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </article>
    @empty
        <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
            <p style="font-size: 2rem; margin-bottom: 0.5rem;">🔔</p>
            <h3>Không có thông báo nào</h3>
            <p class="muted">Hộp thư thông báo của bạn đang trống hoặc không có thông báo phù hợp bộ lọc.</p>
            <div style="margin-top: 0.75rem;">
                <a href="{{ route('notifications.index') }}" class="button secondary">Xóa bộ lọc</a>
            </div>
        </div>
    @endforelse
</div>

<div style="margin-top: 1.5rem;">
    {{ $notifications->links() }}
</div>
</x-layout>
