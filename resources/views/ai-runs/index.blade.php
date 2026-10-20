<x-layout title="Yêu cầu AI — {{ $workspace->name }}">
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
    <div>
        <div class="breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: 0.375rem;">
            <a href="{{ route('workspaces.show', $workspace) }}" style="color: var(--color-primary); font-weight: 500;">{{ $workspace->name }}</a>
            <span style="margin: 0 0.375rem;">/</span>
            <span>Nhật ký trợ lý AI</span>
        </div>
        <h1 style="font-size: 1.625rem; font-weight: 700; margin: 0; color: var(--color-text); letter-spacing: -0.02em;">
            Yêu cầu trợ lý AI (AI Runs)
        </h1>
        <p style="margin: 0.25rem 0 0; font-size: 0.875rem; color: var(--color-text-muted);">
            Lịch sử phân tích báo cáo và sinh phương án lập kế hoạch tự động từ AI
        </p>
    </div>
    <div class="actions" style="display: flex; gap: 0.5rem;">
        <a class="button secondary" href="{{ route('workspaces.dashboard', $workspace) }}" style="display: inline-flex; align-items: center; gap: 0.375rem;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
            Về Dashboard phân tích
        </a>
    </div>
</div>

{{-- Bộ lọc --}}
<section class="card" data-page="ai-run-index" style="padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;">
    <h2 style="font-size: 0.9375rem; font-weight: 700; margin: 0 0 1rem; color: var(--color-text); text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 0.5rem;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
        Lọc danh sách yêu cầu
    </h2>
    <form method="GET" data-form="ai-run-filter" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)) auto; gap: 1rem; align-items: flex-end;">
        <div class="field" style="margin: 0;">
            <label for="status" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.375rem; color: var(--color-text);">Trạng thái</label>
            <select id="status" name="status" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text);">
                <option value="">Tất cả trạng thái</option>
                @foreach(\App\Enums\AiRunStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>
                        {{ \App\Support\UiLabels::text($status) }}
                    </option>
                @endforeach
            </select>
        </div>

        <x-input name="created_from" label="Tạo từ ngày" type="date" :value="$filters['created_from'] ?? ''" />
        <x-input name="created_to" label="Tạo đến ngày (không gồm)" type="date" :value="$filters['created_to'] ?? ''" />

        <button type="submit" class="button" style="padding: 0.5625rem 1.25rem; font-weight: 600; height: fit-content;">
            Lọc yêu cầu
        </button>
    </form>
</section>

{{-- Danh sách AI Runs --}}
<div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem;">
    @forelse($runs as $run)
        <section class="card" data-run-id="{{ $run->id }}" style="padding: 1.25rem 1.5rem; transition: transform var(--transition-fast), box-shadow var(--transition-fast);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.75rem;">
                <div style="display: flex; align-items: center; gap: 0.625rem;">
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #8b5cf6, #ec4899); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                        AI
                    </div>
                    <div>
                        <a href="{{ route('workspaces.ai-runs.show', [$workspace, $run]) }}" style="font-weight: 700; color: var(--color-primary); font-size: 1rem; text-decoration: none;">
                            {{ \App\Support\UiLabels::text($run->operation) }}
                        </a>
                        <div style="font-size: 0.8125rem; color: var(--color-text-muted);">
                            Khởi tạo lúc {{ $run->created_at->setTimezone(auth()->user()->timezone)->format('d/m/Y H:i') }}
                        </div>
                    </div>
                </div>

                <div>
                    @if($run->status->value === 'succeeded')
                        <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #059669; font-weight: 600; font-size: 0.75rem; padding: 0.25rem 0.625rem; border-radius: var(--radius-sm);">
                            ✓ Hoàn tất thành công
                        </span>
                    @elseif($run->status->value === 'failed')
                        <span class="badge" style="background: rgba(239, 68, 68, 0.12); color: #dc2626; font-weight: 600; font-size: 0.75rem; padding: 0.25rem 0.625rem; border-radius: var(--radius-sm);">
                            ✕ Thất bại
                        </span>
                    @elseif(in_array($run->status->value, ['queued', 'running']))
                        <span class="badge" style="background: rgba(245, 158, 11, 0.12); color: #d97706; font-weight: 600; font-size: 0.75rem; padding: 0.25rem 0.625rem; border-radius: var(--radius-sm);">
                            ● Đang thực thi...
                        </span>
                    @else
                        <span class="badge" style="background: rgba(100, 116, 139, 0.12); color: var(--color-text-muted); font-size: 0.75rem; padding: 0.25rem 0.625rem; border-radius: var(--radius-sm);">
                            {{ \App\Support\UiLabels::text($run->status->value) }}
                        </span>
                    @endif
                </div>
            </div>

            <p style="margin: 0; font-size: 0.875rem; color: var(--color-text); line-height: 1.5; background: var(--color-surface-hover); padding: 0.75rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); font-style: italic;">
                "{{ \Illuminate\Support\Str::limit($run->user_prompt, 180) }}"
            </p>

            <div style="display: flex; justify-content: flex-end; margin-top: 0.75rem;">
                <a class="button secondary" href="{{ route('workspaces.ai-runs.show', [$workspace, $run]) }}" style="font-size: 0.8125rem; padding: 0.3125rem 0.75rem;">
                    Xem chi tiết lượt chạy →
                </a>
            </div>
        </section>
    @empty
        <section class="card" style="padding: 3rem 1rem; text-align: center; color: var(--color-text-muted);">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 0.5rem; opacity: 0.5;"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <p style="margin: 0; font-size: 0.9375rem;">Chưa có yêu cầu AI nào được ghi nhận trong phạm vi này.</p>
        </section>
    @endforelse
</div>

{{ $runs->links() }}
</x-layout>
