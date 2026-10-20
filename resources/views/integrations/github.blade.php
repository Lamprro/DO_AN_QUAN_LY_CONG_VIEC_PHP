<x-layout title="GitHub logs — {{ $project->name }}">
<div style="max-width: 960px; margin: 0 auto 2rem;">
    <div class="breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: 0.5rem;">
        <a href="{{ route('workspaces.show', $workspace) }}" style="color: var(--color-primary);">{{ $workspace->name }}</a>
        <span style="margin: 0 0.375rem;">/</span>
        <a href="{{ route('workspaces.projects.show', [$workspace, $project]) }}" style="color: var(--color-primary);">{{ $project->name }}</a>
        <span style="margin: 0 0.375rem;">/</span>
        <span>GitHub Webhook Logs</span>
    </div>

    <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.625rem; font-weight: 700; margin: 0; color: var(--color-text); letter-spacing: -0.02em;">
                Tích hợp GitHub & Nhật ký Webhook
            </h1>
            <p style="margin: 0.25rem 0 0; font-size: 0.875rem; color: var(--color-text-muted);">
                Liên kết commit GitHub với công việc thông qua mã định danh <code>[MÃ_TASK]</code>
            </p>
        </div>
        <div class="actions">
            <a class="button secondary" href="{{ route('workspaces.projects.show', [$workspace, $project]) }}">
                Về dự án
            </a>
        </div>
    </div>

    {{-- Cấu hình Webhook --}}
    <section class="card" style="padding: 1.75rem; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.5rem;">
            <h2 style="font-size: 1.125rem; font-weight: 700; margin: 0; color: var(--color-text); display: flex; align-items: center; gap: 0.5rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/></svg>
                Cấu hình kết nối Webhook
            </h2>
            <div style="display: flex; gap: 0.5rem;">
                <span class="badge" style="background: {{ config('work_management.integrations_enabled') ? 'rgba(16, 185, 129, 0.12)' : 'rgba(239, 68, 68, 0.12)' }}; color: {{ config('work_management.integrations_enabled') ? '#059669' : '#dc2626' }}; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: var(--radius-sm); font-weight: 600;">
                    Toàn cục: {{ config('work_management.integrations_enabled') ? 'Đã bật' : 'Đang tắt' }}
                </span>
                <span class="badge" style="background: var(--color-surface-hover); border: 1px solid var(--color-border); font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: var(--radius-sm);">
                    Kết nối: {{ $connection?->status->value ?? 'Chưa cấu hình' }}
                </span>
            </div>
        </div>

        <div style="background: var(--color-surface-hover); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 1rem; margin-bottom: 1.25rem;">
            <div style="font-size: 0.8125rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 0.375rem;">
                Payload URL (Webhook URL):
            </div>
            <code style="display: block; font-family: var(--font-mono); font-size: 0.875rem; color: var(--color-primary); word-break: break-all;">
                {{ route('github.webhook', $project) }}
            </code>
            <p style="margin: 0.5rem 0 0; font-size: 0.75rem; color: var(--color-text-muted);">
                Content type khi cấu hình webhook: <code>application/json</code>; sự kiện chọn: <code>push</code>.
            </p>
        </div>

        @if(app(\App\Support\ProjectAccess::class)->allows(auth()->user(), $project, 'integration.manage'))
            <form method="post" action="{{ route('workspaces.projects.github.save', [$workspace, $project]) }}" style="display: flex; flex-direction: column; gap: 1rem;">
                @csrf
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr)); gap: 1rem;">
                    <div class="field" style="margin: 0;">
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.375rem; color: var(--color-text);">Repository ID (số)</label>
                        <input name="repository_id" required value="{{ $connection?->account_ref }}" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text);">
                    </div>

                    <div class="field" style="margin: 0;">
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.375rem; color: var(--color-text);">Webhook Secret mới (để trống để giữ nguyên)</label>
                        <input name="webhook_secret" type="password" minlength="32" autocomplete="new-password" placeholder="Tối thiểu 32 ký tự" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text);">
                    </div>

                    <div class="field" style="margin: 0;">
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.375rem; color: var(--color-text);">Trạng thái kết nối</label>
                        <select name="enabled" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text);">
                            <option value="0">Tắt tích hợp</option>
                            <option value="1" @selected($connection?->status->value === 'active')>Bật tích hợp</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 0.5rem;">
                    <button type="submit" class="button" style="padding: 0.625rem 1.25rem; font-weight: 600;">
                        Lưu cấu hình GitHub
                    </button>
                </div>
            </form>
        @endif
    </section>

    {{-- Lịch sử nhận Webhook --}}
    <section class="card" style="padding: 1.75rem;">
        <h2 style="font-size: 1.125rem; font-weight: 700; margin: 0 0 1rem; color: var(--color-text); display: flex; align-items: center; gap: 0.5rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            Nhật ký các lượt nhận Webhook (Delivery Logs)
        </h2>

        @if($latestDelivery)
            <div data-latest-delivery style="background: rgba(99, 102, 241, 0.08); border-left: 3px solid var(--color-primary); padding: 0.875rem 1rem; border-radius: var(--radius-sm); margin-bottom: 1.25rem; font-size: 0.8125rem; color: var(--color-text);">
                <strong>Lần nhận gần nhất:</strong> {{ $latestDelivery->received_at->setTimezone(auth()->user()->timezone)->format('d/m/Y H:i:s') }} ({{ auth()->user()->timezone }}) ·
                Trạng thái: <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #059669; font-size: 0.75rem;">{{ \App\Support\UiLabels::text($latestDelivery->status) }}</span>
                <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 0.25rem;">
                    (Đây là dữ liệu lịch sử đã lưu trữ trong database, không phải kết quả ping mạng trực tiếp tại thời điểm này).
                </div>
            </div>
        @else
            <div style="background: var(--color-surface-hover); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 1rem; margin-bottom: 1.25rem; font-size: 0.875rem; color: var(--color-text-muted); text-align: center;">
                Chưa có lượt nhận nào được lưu cho dự án này.
            </div>
        @endif

        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            @foreach($logs as $log)
                <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 0.875rem 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                    <div>
                        <div style="font-weight: 600; color: var(--color-text); font-size: 0.875rem;">
                            {{ $log->summary }}
                        </div>
                        <div style="font-size: 0.75rem; color: var(--color-text-muted); font-family: var(--font-mono); margin-top: 0.125rem;">
                            Delivery: {{ $log->delivery_id }} · {{ $log->received_at->setTimezone(auth()->user()->timezone)->format('d/m/Y H:i:s') }}
                        </div>
                    </div>
                    <div>
                        <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #059669; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: var(--radius-sm); font-weight: 600;">
                            {{ \App\Support\UiLabels::text($log->status) }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        <div style="margin-top: 1rem;">
            {{ $logs->links() }}
        </div>
    </section>
</div>
</x-layout>
