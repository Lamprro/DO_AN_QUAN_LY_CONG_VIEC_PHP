<x-layout title="Dashboard / Báo cáo">
<div class="page-heading" style="margin-bottom: 1.25rem;">
    <div>
        <p class="eyebrow">{{ $workspace->name }}</p>
        <h1 style="margin: 0;">Dashboard & Báo cáo Điều hành</h1>
        <p class="muted" style="margin-top: 0.25rem;">Tổng quan số liệu hiện trạng, tiến độ công việc và phân tích khối lượng thực tế</p>
    </div>
    <div class="actions">
        <a class="button secondary" href="{{ route('workspaces.show',$workspace) }}">
            ‹ Về Workspace
        </a>
    </div>
</div>

<section class="card" data-page="dashboard" style="border-top: 4px solid var(--primary); padding: 1.5rem; margin-bottom: 1.5rem;">
    <h2 style="font-size: 1.15rem; margin-bottom: 0.75rem;">Bộ lọc kỳ báo cáo</h2>
    <form method="GET" data-form="report-filter">
        @include('reports._filters')
        <div style="margin-top: 1rem;">
            <button type="submit">Cập nhật số liệu</button>
        </div>
    </form>

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border);">
        <div class="actions" style="margin: 0;">
            <a href="{{ route('workspaces.reports.index',$workspace) }}" class="button secondary">
                📁 Danh sách bản báo cáo đã lưu
            </a>
            <a href="{{ route('workspaces.ai-runs.index',$workspace) }}" class="button secondary">
                🤖 Lịch sử yêu cầu AI
            </a>
        </div>

        @if($canCreate)
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <form method="POST" action="{{ route('workspaces.reports.store',$workspace) }}" data-form="report-create" style="margin: 0;">
                    @csrf
                    @include('reports._hidden')
                    <button type="submit" style="background: var(--success) !important;">
                        💾 Lưu snapshot bản báo cáo này
                    </button>
                </form>
                <a class="button secondary" href="{{ route('workspaces.ai-runs.create',array_merge([$workspace],$filters)) }}">
                    ✨ Yêu cầu AI nhận xét số liệu
                </a>
            </div>
        @endif
    </div>
</section>

@include('reports._metrics')
</x-layout>
