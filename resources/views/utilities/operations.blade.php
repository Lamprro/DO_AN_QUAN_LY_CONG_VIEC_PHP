<x-layout title="Giám sát Vận hành">
<div class="page-heading" style="margin-bottom: 1.25rem;">
    <div>
        <p class="eyebrow">{{ $workspace->name }}</p>
        <h1 style="margin: 0;">Giám sát Vận hành (Operations)</h1>
        <p class="muted" style="margin-top: 0.25rem;">
            Theo dõi trạng thái Queue, AI Worker, tác vụ chạy nền và sổ cái lời nhắc tự động
        </p>
    </div>
</div>

<section class="card" style="border-top: 4px solid var(--primary); padding: 1.25rem; margin-bottom: 1.5rem;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 1rem; margin-bottom: 0.75rem;">
        <div style="background: var(--bg-subtle); padding: 0.85rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
            <span class="muted" style="font-size: 0.75rem; text-transform: uppercase;">Queue AI</span>
            <div style="font-weight: 700; font-family: var(--font-mono); color: var(--primary); margin-top: 0.25rem;">
                {{ config('ai.queue') }}
            </div>
        </div>
        <div style="background: var(--bg-subtle); padding: 0.85rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
            <span class="muted" style="font-size: 0.75rem; text-transform: uppercase;">Queue Nhắc việc</span>
            <div style="font-weight: 700; font-family: var(--font-mono); color: var(--primary); margin-top: 0.25rem;">
                work-management-reminders
            </div>
        </div>
        <div style="background: var(--bg-subtle); padding: 0.85rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
            <span class="muted" style="font-size: 0.75rem; text-transform: uppercase;">Database Connection</span>
            <div style="font-weight: 700; font-family: var(--font-mono); color: var(--text); margin-top: 0.25rem;">
                {{ config('database.default') }}
            </div>
        </div>
    </div>

    <p class="muted" style="font-size: 0.8rem; margin: 0;">
        🔒 Chỉ tài khoản được cấp quyền vận hành (Workspace Owner/Manager) mới có thể xem bảng điều khiển này. Hiển thị tối đa 100 AI run và 50 reminder gần nhất do bạn sở hữu. {{ $limited ? '⚠️ Danh sách job đạt giới hạn 1000 hàng nguồn.' : '' }}
    </p>
</section>

<!-- AI Runs / Calls Section -->
<section class="card" style="border-top: 4px solid #8b5cf6;">
    <h2 style="font-size: 1.15rem; margin-bottom: 0.5rem;">🤖 Tác vụ AI (AI Runs / Calls)</h2>
    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
        @forelse($runs as $run)
            <article class="card" style="margin: 0; padding: 0.85rem 1rem; background: var(--bg-subtle) !important; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                <div>
                    <a href="{{ route('workspaces.ai-runs.show',[$workspace,$run]) }}" style="font-weight: 700;">
                        {{ \App\Support\UiLabels::text($run->operation) }}
                    </a>
                    <span class="badge" style="font-family: var(--font-mono); font-size: 0.7rem; margin-left: 0.4rem;">
                        {{ substr($run->id, 0, 8) }}...
                    </span>
                    <span class="status-pill status-{{ strtolower(str_replace('_','-',$run->status->value)) }}" style="font-size: 0.7rem; margin-left: 0.4rem;">
                        {{ \App\Support\UiLabels::text($run->status) }}
                    </span>
                </div>
                <div class="muted" style="font-size: 0.8rem; font-family: var(--font-mono);">
                    {{ $run->calls_count }} lượt gọi · {{ $run->created_at->setTimezone(auth()->user()->timezone)->format('d/m/Y H:i:s') }}
                </div>
            </article>
        @empty
            <p class="muted" style="font-style: italic; margin: 0.5rem 0;">Không có tác vụ AI nào trong danh sách được phép xem.</p>
        @endforelse
    </div>
</section>

<!-- Active Jobs in Queue -->
<section class="card" style="border-top: 4px solid #0284c7;">
    <h2 style="font-size: 1.15rem; margin-bottom: 0.5rem;">⚙️ Hàng đợi tác vụ (Pending Jobs in Queue)</h2>
    @if(!$jobsAvailable)
        <div class="notice warning">
            Queue database sử dụng kết nối khác connection mặc định: chưa đọc được job trực tiếp ở trang này; điều này không đồng nghĩa worker đang dừng hoặc queue đang trống.
        </div>
    @endif

    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
        @forelse($jobs as $job)
            <div style="background: var(--bg-subtle); padding: 0.75rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">
                <div>
                    <strong style="font-family: var(--font-mono); color: var(--primary);">#{{ $job['id'] }}</strong>
                    <span class="badge" style="margin-left: 0.4rem;">{{ $job['attempts'] }} lần lấy</span>
                    <span class="badge" style="{{ $job['reserved'] ? 'background: #eff6ff; color: #1d4ed8;' : '' }}">
                        {{ $job['reserved'] ? 'Đang được giữ xử lý' : 'Chờ trong hàng đợi' }}
                    </span>
                </div>
                <div class="muted" style="font-size: 0.8rem; font-family: var(--font-mono);">
                    Xử lý từ: {{ \Carbon\CarbonImmutable::createFromTimestamp($job['available_at'])->setTimezone(auth()->user()->timezone)->format('d/m/Y H:i:s') }}
                </div>
            </div>
        @empty
            <p class="muted" style="font-style: italic; margin: 0.5rem 0;">Không có job pending nào tương ứng trong tập dữ liệu đã đọc.</p>
        @endforelse
    </div>
</section>

<!-- Dead-letter & Failed Jobs -->
<section class="card" style="border-top: 4px solid var(--danger);">
    <h2 style="font-size: 1.15rem; margin-bottom: 0.5rem; color: var(--danger);">⚠️ Job lỗi đã lưu (Dead-letter Failed Jobs)</h2>
    <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1.5rem;">
        @forelse($failedJobs as $job)
            <div style="background: var(--danger-bg); border: 1px solid var(--danger-border); padding: 0.75rem 1rem; border-radius: var(--radius-sm); display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">
                <div>
                    <strong style="color: var(--danger); font-family: var(--font-mono);">#{{ $job['id'] }}</strong>
                    <span class="muted" style="margin-left: 0.5rem;">Lỗi lúc: {{ $job['failed_at'] }} UTC</span>
                </div>
                <small class="muted">Mở tác vụ AI tương ứng để thử lại theo số lượt cho phép.</small>
            </div>
        @empty
            <p class="muted" style="font-style: italic; margin: 0.5rem 0;">Không có job lỗi (dead-letter) trong tập dữ liệu kiểm tra.</p>
        @endforelse
    </div>

    <h2 style="font-size: 1.15rem; margin-bottom: 0.5rem;">📋 Sổ cái lời nhắc (Reminder Ledger)</h2>
    <div style="display: flex; flex-direction: column; gap: 0.4rem;">
        @forelse($reminders as $r)
            <div style="background: var(--bg-subtle); padding: 0.5rem 0.85rem; border-radius: var(--radius-sm); font-size: 0.85rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <span class="badge">{{ $r->kind }}</span>
                    <span class="badge" style="margin-left: 0.35rem;">{{ $r->status }}</span>
                    <span style="font-family: var(--font-mono); margin-left: 0.5rem;">{{ $r->local_date->format('d/m/Y') }}</span>
                </div>
                @if($r->skip_reason)
                    <small class="muted">{{ $r->skip_reason }}</small>
                @endif
            </div>
        @empty
            <p class="muted" style="font-style: italic; margin: 0.5rem 0;">Không có lời nhắc nào được phép đọc.</p>
        @endforelse
    </div>
</section>
</x-layout>
