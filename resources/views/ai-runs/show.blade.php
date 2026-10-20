<x-layout title="Trạng thái yêu cầu AI — {{ $workspace->name }}">
<div style="max-width: 960px; margin: 0 auto 2rem;">
    <div class="breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: 0.5rem;">
        <a href="{{ route('workspaces.show', $workspace) }}" style="color: var(--color-primary);">{{ $workspace->name }}</a>
        <span style="margin: 0 0.375rem;">/</span>
        <a href="{{ route('workspaces.ai-runs.index', $workspace) }}" style="color: var(--color-primary);">Nhật ký AI</a>
        <span style="margin: 0 0.375rem;">/</span>
        <span>Chi tiết lượt chạy</span>
    </div>

    {{-- Thẻ tổng quan yêu cầu AI --}}
    <section class="card" data-page="ai-run-show" data-run-id="{{ $run->id }}" style="padding: 1.75rem; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
            <div>
                <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.375rem;">
                    <span class="badge" style="background: rgba(139, 92, 246, 0.12); color: #8b5cf6; font-weight: 700; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: var(--radius-sm);">
                        AI Assistant
                    </span>
                    <span class="badge" style="background: var(--color-surface-hover); border: 1px solid var(--color-border); font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: var(--radius-sm); font-family: var(--font-mono);">
                        {{ $calls->count() }}/{{ config('ai.max_attempts') }} lượt thử
                    </span>
                </div>
                <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0; color: var(--color-text); letter-spacing: -0.01em;">
                    {{ \App\Support\UiLabels::text($run->operation) }}
                </h1>
            </div>

            <div>
                @if($run->status->value === 'succeeded')
                    <span class="badge" data-ai-status="{{ $run->status->value }}" style="background: rgba(16, 185, 129, 0.12); color: #059669; font-weight: 700; font-size: 0.8125rem; padding: 0.3125rem 0.75rem; border-radius: var(--radius-sm);">
                        ✓ {{ \App\Support\UiLabels::text($run->status->value) }}
                    </span>
                @elseif($run->status->value === 'failed')
                    <span class="badge" data-ai-status="{{ $run->status->value }}" style="background: rgba(239, 68, 68, 0.12); color: #dc2626; font-weight: 700; font-size: 0.8125rem; padding: 0.3125rem 0.75rem; border-radius: var(--radius-sm);">
                        ✕ {{ \App\Support\UiLabels::text($run->status->value) }}
                    </span>
                @else
                    <span class="badge" data-ai-status="{{ $run->status->value }}" style="background: rgba(245, 158, 11, 0.12); color: #d97706; font-weight: 700; font-size: 0.8125rem; padding: 0.3125rem 0.75rem; border-radius: var(--radius-sm);">
                        ● {{ \App\Support\UiLabels::text($run->status->value) }}
                    </span>
                @endif
            </div>
        </div>

        @include('ai-runs._configuration')

        @if(in_array($run->status->value, ['queued', 'running'], true))
            <div style="background: rgba(245, 158, 11, 0.08); border-left: 3px solid var(--color-warning); padding: 0.875rem 1rem; border-radius: var(--radius-sm); margin: 1rem 0; font-size: 0.875rem; color: var(--color-text);" role="status">
                ⏳ Yêu cầu đang được xếp hàng hoặc đang xử lý trong worker queue. Bạn có thể bấm "Làm mới trạng thái" hoặc chủ động hủy yêu cầu nếu cần.
            </div>
        @endif

        {{-- Lời nhắc người dùng (Prompt) --}}
        <div style="margin: 1.25rem 0;">
            <div style="font-size: 0.8125rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); margin-bottom: 0.375rem;">
                Yêu cầu / Prompt ban đầu:
            </div>
            <div style="white-space: pre-wrap; background: var(--color-surface-hover); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 1rem; font-size: 0.9375rem; color: var(--color-text); line-height: 1.6;">{{ $run->user_prompt }}</div>
        </div>

        {{-- Thông tin thời gian --}}
        <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; font-size: 0.8125rem; color: var(--color-text-muted); padding: 0.75rem 0; border-top: 1px solid var(--color-border); border-bottom: 1px solid var(--color-border); margin-bottom: 1.25rem;">
            <div>Bắt đầu: <strong style="color: var(--color-text);">{{ $run->started_at?->setTimezone(auth()->user()->timezone)->format('d/m/Y H:i:s') ?? 'Chưa bắt đầu' }}</strong></div>
            <div>Kết thúc: <strong style="color: var(--color-text);">{{ $run->finished_at?->setTimezone(auth()->user()->timezone)->format('d/m/Y H:i:s') ?? 'Chưa kết thúc' }}</strong></div>
        </div>

        @if($run->error_message)
            <div class="notice errors" role="alert" style="background: rgba(239, 68, 68, 0.08); border-left: 4px solid var(--color-danger); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1.25rem; color: #dc2626; font-size: 0.875rem;">
                <strong>Lỗi phát sinh:</strong> {{ $run->error_message }}
            </div>
        @endif

        <div class="actions" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <a class="button" href="{{ route('workspaces.ai-runs.show', [$workspace, $run]) }}" style="font-size: 0.8125rem; padding: 0.4375rem 0.875rem;">
                ↻ Làm mới trạng thái
            </a>
            <a class="button secondary" href="{{ route('workspaces.ai-runs.index', $workspace) }}" style="font-size: 0.8125rem; padding: 0.4375rem 0.875rem;">
                Danh sách yêu cầu
            </a>

            @can('manage', $run)
                @if(in_array($run->status->value, ['queued', 'running'], true))
                    <form method="POST" action="{{ route('workspaces.ai-runs.cancel', [$workspace, $run]) }}" data-form="ai-run-cancel" style="margin: 0;">
                        @csrf
                        <input type="hidden" name="expected_status" value="{{ $run->status->value }}">
                        <button type="submit" class="button danger" style="font-size: 0.8125rem; padding: 0.4375rem 0.875rem;">
                            Hủy yêu cầu
                        </button>
                    </form>
                @endif

                @if($run->status->value === 'failed' && $calls->count() < config('ai.max_attempts'))
                    <form method="POST" action="{{ route('workspaces.ai-runs.retry', [$workspace, $run]) }}" data-form="ai-run-retry" style="margin: 0;">
                        @csrf
                        <input type="hidden" name="expected_status" value="failed">
                        <button type="submit" class="button" style="font-size: 0.8125rem; padding: 0.4375rem 0.875rem; background: var(--color-warning);">
                            Thử lại lượt mới
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </section>

    {{-- Lịch sử các cuộc gọi API AI --}}
    <section class="card" style="padding: 1.75rem; margin-bottom: 1.5rem;">
        <h2 style="font-size: 1.125rem; font-weight: 700; margin: 0 0 1.25rem; color: var(--color-text); display: flex; align-items: center; gap: 0.5rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            Chi tiết các lượt thực thi API ({{ $calls->count() }})
        </h2>

        <div style="display: flex; flex-direction: column; gap: 1rem;">
            @forelse($calls as $call)
                <article data-call-id="{{ $call->id }}" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1.25rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span class="badge" style="background: rgba(99, 102, 241, 0.1); color: var(--color-primary); font-weight: 700; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: var(--radius-sm);">
                                Lượt #{{ $call->attempt_no }}
                            </span>
                            <span style="font-size: 0.875rem; font-weight: 600; color: var(--color-text);">
                                {{ $call->provider }} / <span style="font-family: var(--font-mono); color: var(--color-primary);">{{ $call->model }}</span>
                            </span>
                        </div>
                        <div>
                            @if($call->status->value === 'succeeded')
                                <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #059669; font-weight: 600; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: var(--radius-sm);">
                                    ✓ Thành công
                                </span>
                            @else
                                <span class="badge" style="background: rgba(239, 68, 68, 0.12); color: #dc2626; font-weight: 600; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: var(--radius-sm);">
                                    {{ \App\Support\UiLabels::text($call->status) }}
                                </span>
                            @endif
                        </div>
                    </div>

                    @if($call->error_message)
                        <div style="background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: var(--radius-sm); padding: 0.75rem; font-size: 0.8125rem; color: #dc2626; margin-bottom: 0.75rem;">
                            {{ $call->error_message }}
                        </div>
                    @endif

                    @if($run->status->value === 'succeeded' && $call->status->value === 'succeeded')
                        <div style="margin-top: 0.5rem;">
                            <h3 style="font-size: 0.875rem; font-weight: 700; color: var(--color-text); margin: 0 0 0.5rem; display: flex; align-items: center; gap: 0.375rem;">
                                💬 Phản hồi phân tích từ AI:
                            </h3>
                            <div style="white-space: pre-wrap; font-size: 0.9375rem; line-height: 1.6; color: var(--color-text); background: var(--color-surface-hover); padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border);">{{ $call->response_payload['text'] ?? '' }}</div>
                        </div>
                    @endif
                </article>
            @empty
                <div style="text-align: center; padding: 2rem 1rem; color: var(--color-text-muted);">
                    <p style="margin: 0; font-size: 0.875rem;">Chưa có lượt xử lý nào được thực thi.</p>
                </div>
            @endforelse
        </div>
    </section>

    {{-- Kết quả phụ trợ: Báo cáo số liệu hoặc Phương án kế hoạch --}}
    @if($run->operation->value === 'report')
        @php($metrics = $run->context_snapshot['metrics'])
        <section class="card" style="padding: 1.75rem;">
            <h2 style="font-size: 1.125rem; font-weight: 700; margin: 0 0 1rem; color: var(--color-text);">Số liệu đính kèm báo cáo</h2>
            @include('reports._metrics')
        </section>
    @else
        <section class="card" style="padding: 1.75rem;">
            <h2 style="font-size: 1.125rem; font-weight: 700; margin: 0 0 1rem; color: var(--color-text);">Kết quả kế hoạch phát sinh</h2>
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                @forelse($run->plans as $plan)
                    <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span style="font-weight: 700; color: var(--color-text); font-size: 1rem;">{{ $plan->name }}</span>
                            <span class="badge" style="background: rgba(99, 102, 241, 0.1); color: var(--color-primary); font-size: 0.75rem; padding: 0.125rem 0.375rem; border-radius: var(--radius-xs); margin-left: 0.5rem;">
                                v{{ $plan->version_no }} (Bản nháp)
                            </span>
                        </div>
                        <a class="button" href="{{ route('workspaces.plans.show', [$workspace, $plan]) }}" style="font-size: 0.8125rem; padding: 0.375rem 0.75rem;">
                            Xem phương án →
                        </a>
                    </div>
                @empty
                    <p class="muted" style="margin: 0; font-size: 0.875rem;">Chưa tạo bản nháp. Chức năng Assistance chỉ phản hồi tư vấn phân tích, không trực tiếp thay đổi cơ sở dữ liệu.</p>
                @endforelse
            </div>
        </section>
    @endif
</div>
</x-layout>
