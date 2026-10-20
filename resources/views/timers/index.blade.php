<x-layout title="Giờ làm thực tế">
<div class="page-heading" style="margin-bottom: 1.25rem;">
    <div>
        <p class="eyebrow">TIME TRACKING</p>
        <h1 style="margin: 0;">Giờ làm thực tế của tôi</h1>
        <p class="muted" style="margin-top: 0.25rem;">
            Theo dõi nhật ký thời gian làm việc, đối chiếu kế hoạch và điều chỉnh phiên ghi giờ khi cần thiết
        </p>
    </div>
</div>

<section class="card" data-page="timers" style="border-top: 4px solid var(--primary);">
    <div class="notice" style="margin-bottom: 1.5rem;">
        <span>⏱️</span>
        <div>Timer ghi nhận thời gian thực tế cho các công việc WORK đang ở trạng thái Đang thực hiện (IN_PROGRESS) được giao cho bạn. Dừng timer vẫn luôn được phép ngay cả khi thay đổi quyền truy cập task.</div>
    </div>

    <div style="display: flex; flex-direction: column; gap: 1rem;">
        @forelse($entries as $entry)
            <article class="card" style="margin: 0; padding: 1.25rem; background: var(--bg-subtle) !important; border-left: 4px solid {{ !$entry->ended_at ? '#ef4444' : 'var(--primary)' }};">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.75rem;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.4rem;">
                            @if(!$entry->ended_at)
                                <span class="badge" style="background: #fff1f2; color: #be123c; border-color: #fecdd3; font-weight: 700;">
                                    ● Đang chạy
                                </span>
                            @else
                                <span class="badge" style="background: #ecfdf5; color: #047857; border-color: #a7f3d0; font-weight: 700;">
                                    ✓ Đã hoàn tất
                                </span>
                            @endif
                            <span class="muted" style="font-family: var(--font-mono); font-size: 0.8rem;">
                                {{ $entry->started_at->timezone(auth()->user()->timezone)->format('d/m/Y H:i:s') }} → {{ $entry->ended_at ? $entry->ended_at->timezone(auth()->user()->timezone)->format('H:i:s') : 'Hiện tại' }}
                            </span>
                        </div>

                        <h3 style="margin: 0; font-size: 1.05rem;">
                            @if(\Illuminate\Support\Facades\Gate::allows('view',$entry->task))
                                <span style="font-family: var(--font-mono); color: var(--primary); font-weight: 700;">{{ $entry->task->code }}</span> — {{ $entry->task->title }}
                            @else
                                <span class="muted" style="font-style: italic;">Công việc không còn quyền xem</span>
                            @endif
                        </h3>
                    </div>

                    <div style="text-align: right;">
                        @if(!$entry->ended_at)
                            <form method="post" action="{{ route('timers.stop',$entry) }}" style="margin: 0;">
                                @csrf
                                <button class="danger" style="min-height: 36px; padding: 0.35rem 0.85rem; font-size: 0.85rem;">
                                    ■ Dừng ghi giờ
                                </button>
                            </form>
                        @else
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div style="font-family: var(--font-mono); font-weight: 800; font-size: 1.15rem; color: var(--text);">
                                    {{ round($entry->started_at->diffInSeconds($entry->ended_at)/60,2) }} <small style="font-size: 0.8rem; font-weight: 500;">phút</small>
                                </div>
                                @if(app(\App\Services\TimeAdjustmentService::class)->allowed(auth()->user(),$entry))
                                    <a href="{{ route('timers.adjust',$entry) }}" class="button secondary" style="min-height: 34px; padding: 0.35rem 0.75rem; font-size: 0.8rem;">
                                        ✏️ Điều chỉnh
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div style="text-align: center; padding: 3rem 1.5rem;">
                <p style="font-size: 2rem; margin-bottom: 0.5rem;">⏱️</p>
                <h3>Chưa có phiên ghi giờ nào</h3>
                <p class="muted">Bắt đầu công việc ở trạng thái Đang thực hiện và kích hoạt Timer để theo dõi thời gian thực tế.</p>
            </div>
        @endforelse
    </div>

    <div style="margin-top: 1.5rem;">
        {{ $entries->links() }}
    </div>
</section>
</x-layout>
