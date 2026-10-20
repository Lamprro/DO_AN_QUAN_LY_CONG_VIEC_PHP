<section class="card" style="padding: 1.5rem; margin-top: 1.5rem; border: 1px solid var(--color-border); border-radius: var(--radius-md);">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem;">
        <div>
            <h2 style="font-size: 1.125rem; font-weight: 700; margin: 0; color: var(--color-text); display: flex; align-items: center; gap: 0.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--color-primary);"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Phân tích hạn chót & Điều kiện cản trở
            </h2>
            <div style="font-size: 0.8125rem; color: var(--color-text-muted); margin-top: 0.25rem;">
                Múi giờ tham chiếu: <strong>{{ $workspace->timezone }}</strong>
            </div>
        </div>

        <div>
            @if($deadlineInsight['overdue'])
                <span class="badge" style="background: rgba(239, 68, 68, 0.12); color: #dc2626; font-size: 0.8125rem; font-weight: 700; padding: 0.25rem 0.625rem; border-radius: var(--radius-sm); display: inline-flex; align-items: center; gap: 0.375rem;">
                    ⚠️ WORK ĐANG QUÁ HẠN
                </span>
            @else
                <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #059669; font-size: 0.8125rem; font-weight: 600; padding: 0.25rem 0.625rem; border-radius: var(--radius-sm); display: inline-flex; align-items: center; gap: 0.375rem;">
                    ✓ Không quá hạn
                </span>
            @endif
        </div>
    </div>

    <div style="background: var(--color-surface-hover); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 0.875rem 1rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <span style="font-size: 0.875rem; color: var(--color-text);">Hạn chót thiết lập:</span>
        <strong style="font-size: 0.9375rem; font-family: var(--font-mono); color: {{ $deadlineInsight['overdue'] ? '#dc2626' : 'var(--color-primary)' }};">
            {{ $task->deadline_at?->setTimezone($workspace->timezone)->format('d/m/Y H:i') ?? 'Chưa thiết lập hạn chót' }}
        </strong>
    </div>

    {{-- Điều kiện cản trở --}}
    <div style="margin-bottom: 1rem;">
        <h3 style="font-size: 0.875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); margin: 0 0 0.5rem;">
            Điều kiện cản trở phát hiện
        </h3>
        @forelse($deadlineInsight['conditions'] as $condition)
            <div style="background: rgba(245, 158, 11, 0.08); border-left: 3px solid var(--color-warning); padding: 0.625rem 0.875rem; border-radius: var(--radius-xs); margin-bottom: 0.5rem; font-size: 0.875rem; color: var(--color-text);">
                {{ $condition }}
            </div>
        @empty
            <div style="background: rgba(16, 185, 129, 0.05); border-left: 3px solid var(--color-success); padding: 0.625rem 0.875rem; border-radius: var(--radius-xs); font-size: 0.875rem; color: var(--color-text-muted);">
                ✓ Không phát hiện điều kiện cản trở từ blocker / phụ thuộc FS / duyệt review / phân công.
            </div>
        @endforelse
    </div>

    {{-- Lịch sử đổi hạn --}}
    <details style="border-top: 1px solid var(--color-border); padding-top: 0.75rem;">
        <summary style="font-size: 0.875rem; font-weight: 600; color: var(--color-primary); cursor: pointer; user-select: none;">
            Lịch sử 20 lần thay đổi hạn chót gần nhất
        </summary>
        <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-top: 0.75rem;">
            @forelse($deadlineInsight['history'] as $history)
                <article style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 0.75rem;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: 0.25rem;">
                        <span style="font-weight: 600; color: var(--color-text);">{{ $history->actor?->name ?? 'Hệ thống' }}</span>
                        <span>{{ $history->created_at->setTimezone($workspace->timezone)->format('d/m/Y H:i:s') }}</span>
                    </div>
                    <div style="font-family: var(--font-mono); font-size: 0.8125rem; color: var(--color-primary); margin-bottom: 0.25rem;">
                        {{ $history->data['before']['deadline_at'] ?? 'Chưa có' }} → {{ $history->data['after']['deadline_at'] ?? 'Chưa có' }}
                    </div>
                    <div style="font-size: 0.8125rem; color: var(--color-text); font-style: italic;">
                        Lý do: "{{ $history->data['reason'] }}"
                    </div>
                </article>
            @empty
                <p class="muted" style="font-size: 0.8125rem; margin: 0.5rem 0 0;">Chưa có sự kiện đổi hạn được ghi riêng.</p>
            @endforelse
        </div>
    </details>
</section>
