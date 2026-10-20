<x-layout title="Nhắc việc của tôi">
<div class="page-heading" style="margin-bottom: 1.25rem;">
    <div>
        <p class="eyebrow">{{ $workspace->name }}</p>
        <h1 style="margin: 0;">Nhắc việc của tôi (Reminders)</h1>
        <p class="muted" style="margin-top: 0.25rem;">
            Quét và theo dõi các lời nhắc tự động in-app trước hạn 24 giờ hoặc khi trạng thái cần phê duyệt
        </p>
    </div>
</div>

<section class="card" style="border-top: 4px solid var(--primary); padding: 1.5rem; margin-bottom: 1.5rem;">
    <p style="margin-top: 0; color: var(--text);">
        Hệ thống tự động nhắc việc In-app cho các công việc có hạn trong vòng <strong>24 giờ</strong> hoặc đã quá hạn; và công việc chờ duyệt (REVIEW) quá <strong>60 phút</strong>. Mỗi công việc chỉ nhắc 1 lần cho mỗi loại và ngày tương ứng.
    </p>

    <div style="background: var(--bg-subtle); padding: 1rem 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-light); margin: 1rem 0;">
        <form method="post" action="{{ route('workspaces.reminders.scan',$workspace) }}" data-form="reminder-scan" style="margin: 0;">
            @csrf
            <button type="submit" style="font-weight: 700;">
                🔍 Quét nhắc việc của tôi ngay
            </button>
        </form>
    </div>

    <p class="muted" style="font-size: 0.8rem; margin: 0;">
        ℹ️ Thao tác quét chỉ đưa lời nhắc vào hàng đợi (Queue). Thông báo sẽ xuất hiện trong Inbox sau khi Worker xử lý.
    </p>
</section>

<section class="card" style="border-top: 4px solid #0284c7;">
    <h2 style="font-size: 1.15rem; margin-bottom: 0.5rem;">Nhật ký nhắc việc gần nhất (Tối đa 50 bản ghi)</h2>
    <p class="muted" style="font-size: 0.85rem; margin-bottom: 1rem;">Chỉ hiển thị các lời nhắc thuộc tài khoản của bạn và còn quyền xem công việc nguồn.</p>

    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        @forelse($reminders as $r)
            <article class="card" style="margin: 0; padding: 1rem 1.25rem; background: var(--bg-subtle) !important; border-left: 4px solid {{ $r->status==='sent' ? 'var(--success)' : ($r->status==='failed' ? 'var(--danger)' : 'var(--primary)') }};">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.25rem;">
                            <span class="badge" style="{{ $r->kind==='due' ? 'background: #fff1f2; color: #be123c;' : 'background: #eff6ff; color: #1d4ed8;' }}">
                                {{ $r->kind==='due' ? '⏰ Nhắc hạn chót' : '👁️ Nhắc phê duyệt' }}
                            </span>
                            <span class="badge" style="font-family: var(--font-mono); font-size: 0.75rem;">
                                {{ $r->local_date->format('d/m/Y') }} ({{ $r->timezone }})
                            </span>
                            <span class="badge" style="{{ $r->status==='sent' ? 'background: #ecfdf5; color: #047857;' : ($r->status==='failed' ? 'background: #fff1f2; color: #be123c;' : '') }}">
                                {{ ['queued'=>'Đang chờ worker','sent'=>'Đã gửi in-app','failed'=>'Job lỗi, chưa gửi','skipped'=>'Đã bỏ qua'][$r->status] ?? $r->status }}
                            </span>
                            @if($r->skip_reason)
                                <small class="muted" style="font-size: 0.75rem;">({{ $r->skip_reason }})</small>
                            @endif
                        </div>
                        <div style="font-weight: 700; font-size: 0.95rem;">
                            <a href="{{ route($r->task->project_id?'workspaces.projects.tasks.show':'workspaces.tasks.show',$r->task->project_id?[$workspace,$r->task->project_id,$r->task_id]:[$workspace,$r->task_id]) }}" style="text-decoration: none;">
                                <span style="font-family: var(--font-mono); color: var(--primary);">{{ $r->task->code }}</span> — {{ $r->task->title }}
                            </a>
                        </div>
                    </div>
                </div>
            </article>
        @empty
            <div style="text-align: center; padding: 2.5rem 1rem;">
                <p style="font-size: 2rem; margin-bottom: 0.5rem;">🔔</p>
                <h3>Chưa có lời nhắc nào</h3>
                <p class="muted">Bấm "Quét nhắc việc của tôi ngay" để kiểm tra các nhiệm vụ sắp đến hạn hoặc đang chờ duyệt.</p>
            </div>
        @endforelse
    </div>
</section>
</x-layout>
