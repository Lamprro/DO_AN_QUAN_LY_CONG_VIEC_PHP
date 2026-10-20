<x-layout title="Kế hoạch">
<div class="page-heading" style="margin-bottom: 1.25rem;">
    <div>
        <p class="eyebrow">{{ $project?->name ?? $workspace->name }}</p>
        <h1 style="margin: 0;">Kế hoạch & Phiên bản (Plans)</h1>
        <p class="muted" style="margin-top: 0.25rem;">Soạn thảo, so sánh thay đổi và áp dụng các phiên bản kế hoạch vào dự án</p>
    </div>
    <div class="actions">
        @if(app(\App\Services\PlanService::class)->allowed(auth()->user(),$workspace,$project,'plan.create'))
            <a class="button secondary" href="{{ route('workspaces.planning-ai.create',[$workspace,'project_id'=>$project?->id]) }}">
                🤖 AI / Trợ lý blocker
            </a>
            <a class="button" href="{{ route('workspaces.plans.create',[$workspace,'project_id'=>$project?->id]) }}">
                <span>+</span> Tạo bản nháp mới
            </a>
        @endif
        <a class="button secondary" href="{{ route('workspaces.plans.impact',[$workspace,'project_id'=>$project?->id]) }}">
            📊 Phân tích ảnh hưởng
        </a>
    </div>
</div>

<section class="card table-wrap" data-page="plans-index" style="border-top: 4px solid var(--primary);">
    <table>
        <thead>
            <tr>
                <th>Tên Kế Hoạch</th>
                <th style="width: 140px;">Phiên Bản</th>
                <th>Trạng Thái & Áp Dụng</th>
            </tr>
        </thead>
        <tbody>
            @forelse($plans as $plan)
                <tr>
                    <td>
                        <div style="font-weight: 700; font-size: 0.95rem;">
                            <a href="{{ route('workspaces.plans.show',[$workspace,$plan]) }}" style="text-decoration: none;">
                                {{ $plan->name }}
                            </a>
                        </div>
                    </td>
                    <td>
                        <span class="badge" style="font-family: var(--font-mono); font-weight: 700; background: var(--bg-subtle);">
                            v{{ $plan->version_no }}
                        </span>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                            <span class="status-pill status-{{ strtolower(str_replace('_','-',$plan->status->value)) }}">
                                {{ \App\Support\UiLabels::text($plan->status->value) }}
                            </span>
                            @if($plan->is_active)
                                <span class="badge" style="background: #ecfdf5; color: #047857; border-color: #a7f3d0; font-weight: 700;">
                                    ● Đang áp dụng
                                </span>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="text-align: center; padding: 3rem 1.5rem;">
                        <p style="font-size: 2rem; margin-bottom: 0.5rem;">📑</p>
                        <h3>Chưa có bản kế hoạch nào</h3>
                        <p class="muted">Hãy tạo bản nháp đầu tiên để thiết lập cấu trúc công việc, quan hệ phụ thuộc và khung giờ.</p>
                        @if(app(\App\Services\PlanService::class)->allowed(auth()->user(),$workspace,$project,'plan.create'))
                            <div style="margin-top: 1rem;">
                                <a class="button" href="{{ route('workspaces.plans.create',[$workspace,'project_id'=>$project?->id]) }}">
                                    Tạo kế hoạch nháp mới
                                </a>
                            </div>
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</section>

<div style="margin-top: 1.5rem;">
    {{ $plans->links() }}
</div>
</x-layout>
