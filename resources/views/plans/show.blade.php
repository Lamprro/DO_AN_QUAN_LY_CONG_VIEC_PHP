<x-layout title="Chi tiết kế hoạch">
<div style="margin-bottom: 1rem;">
    <a href="{{ route('workspaces.plans.index',[$workspace,'project_id'=>$project?->id]) }}" style="font-size: 0.875rem; font-weight: 600;">
        ‹ Danh sách kế hoạch
    </a>
</div>

<section class="card" data-page="plan-show" style="border-top: 4px solid var(--primary);">
    <div class="page-heading">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.4rem;">
                <span class="badge" style="font-family: var(--font-mono); font-weight: 800; background: var(--primary-light); color: var(--primary);">
                    Phiên bản v{{ $plan->version_no }}
                </span>
                <x-status :value="$plan->status" />
                <span class="badge" style="{{ $plan->is_active ? 'background: #ecfdf5; color: #047857; font-weight: 700;' : '' }}">
                    {{ $plan->is_active?'● Đang áp dụng':($plan->applied_at?'Đã áp dụng trước đó':'Chưa áp dụng') }}
                </span>
                <span class="badge">
                    Nguồn: {{ ($plan->data['source'] ?? '')==='ai'?'🤖 Đề xuất AI':'Thủ công / Thuật toán Local' }}
                </span>
            </div>
            <h1 style="margin-bottom: 0.35rem;">{{ $plan->name }}</h1>
            <p class="muted" style="margin: 0; font-size: 0.85rem;">
                {{ $plan->reviewed_at ? 'Quyết định duyệt: '.$plan->reviewed_at->setTimezone($workspace->timezone)->format('d/m/Y H:i') : 'Chưa có quyết định duyệt chính thức' }} · Múi giờ: <strong>{{ $workspace->timezone }}</strong>
            </p>
        </div>
    </div>

    <div class="notice" style="margin: 1.25rem 0;">
        <span>ℹ️</span>
        <div>Phê duyệt kế hoạch chưa thay đổi công việc thực tế. Việc áp dụng (Apply) chỉ cập nhật các công việc chưa bắt đầu (TODO); việc loại bỏ dòng khỏi đề xuất sẽ không tự động xóa task hoặc dependency đã tồn tại.</div>
    </div>

    <div class="actions" style="margin-bottom: 1rem;">
        @if($canRevise)
            <a class="button" href="{{ route('workspaces.plans.create',[$workspace,'project_id'=>$project?->id,'base_id'=>$plan->id]) }}">
                📝 Sửa bằng phiên bản mới
            </a>
        @endif
        @if($canPreview && !$plan->applied_at)
            <a class="button secondary" href="{{ route('workspaces.plans.preview',[$workspace,$plan]) }}">
                👁️ Xem trước tác động áp dụng
            </a>
        @endif
    </div>

    @if(!empty($actions))
        <div class="actions review-actions" style="padding-top: 1rem; border-top: 1px solid var(--border);">
            @foreach($actions as $action=>$label)
                @if($action!=='apply')
                    <form method="post" action="{{ route('workspaces.plans.action',[$workspace,$plan,$action]) }}" data-form="plan-{{ $action }}" data-confirm="{{ $label }} phiên bản {{ $plan->version_no }}?" style="margin: 0; display: inline-flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        @csrf
                        <input type="hidden" name="expected_status" value="{{ $plan->status->value }}">
                        @if($action==='reject')
                            <input id="reject-reason" name="reason" required maxlength="4000" placeholder="Nhập lý do từ chối..." style="min-width: 200px; padding: 0.4rem 0.75rem; font-size: 0.85rem;">
                        @endif
                        <button type="submit" class="{{ $action==='reject'?'danger':'' }}">
                            {{ $label }}
                        </button>
                    </form>
                @endif
            @endforeach
        </div>
    @endif

    @if($plan->change_summary)
        <div style="background: var(--bg-subtle); padding: 0.85rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light); margin-top: 1rem; font-size: 0.9rem;">
            <strong>Tóm tắt thay đổi:</strong> {{ $plan->change_summary }}
        </div>
    @endif
</section>

<!-- Diff Comparison Section -->
<section class="card" style="border-top: 4px solid #0284c7;">
    <h2 style="font-size: 1.25rem; margin-bottom: 0.35rem;">
        Đối chiếu thay đổi {{ $parent?'(v'.$parent->version_no.' → v'.$plan->version_no.')':'(Đề xuất mới)' }}
    </h2>
    <p class="muted" style="margin-bottom: 1.25rem; font-size: 0.85rem;">
        “Bỏ khỏi đề xuất” không đồng nghĩa với việc xóa công việc đã có trên hệ thống. Khung giờ và quan hệ phụ thuộc được so sánh bên dưới.
    </p>

    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        @foreach($diff['rows'] as $row)
            <article class="diff-row diff-{{ $row['kind'] }}">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <h3 style="margin: 0; font-size: 1.05rem;">
                        <span style="font-family: var(--font-mono); color: var(--primary);">{{ $row['ref'] }}</span> · {{ $row['title'] }}
                    </h3>
                    <span class="badge" style="{{ $row['kind']==='added' ? 'background: #ecfdf5; color: #047857;' : ($row['kind']==='changed' ? 'background: #fffbeb; color: #b45309;' : ($row['kind']==='omitted' ? 'background: #fff1f2; color: #be123c;' : '')) }}">
                        {{ ['added'=>'Thêm vào đề xuất','changed'=>'Có thay đổi','unchanged'=>'Giữ nguyên','omitted'=>'Bỏ khỏi đề xuất'][$row['kind']] }}
                    </span>
                </div>

                @if($row['fields'])
                    <dl class="diff-fields" style="background: var(--surface); padding: 0.85rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light); font-size: 0.85rem; margin: 0.5rem 0 0 0;">
                        @foreach($row['fields'] as $field=>$change)
                            <dt style="font-weight: 600;">
                                {{ ['sort_order'=>'Thứ tự trong nhóm','position'=>'Thứ tự','title'=>'Tiêu đề','description'=>'Mô tả','task_type'=>'Loại','parent_ref'=>'Nhóm cha','priority'=>'Ưu tiên','estimate_minutes'=>'Ước lượng (phút)','assignee_id'=>'Người thực hiện','reviewer_id'=>'Người duyệt'][$field] }}:
                            </dt>
                            <dd style="margin: 0;">
                                <span class="muted">{{ str_ends_with($field,'_id')?($users[$change['before']]??'Chưa phân công / không còn phù hợp'):\App\Support\UiLabels::text($change['before']??'Chưa có') }}</span>
                                <span style="margin: 0 0.4rem; color: var(--primary); font-weight: 700;">→</span>
                                <strong>{{ str_ends_with($field,'_id')?($users[$change['after']]??'Chưa phân công / không còn phù hợp'):\App\Support\UiLabels::text($change['after']??'Chưa có') }}</strong>
                            </dd>
                        @endforeach
                    </dl>
                @endif
            </article>
        @endforeach
    </div>

    <!-- Dependencies & Slots Comparison -->
    @foreach(['dependencies'=>'Quan hệ chờ (FS)','slots'=>'Khung giờ dự kiến (Slots)'] as $key=>$label)
        <h3 style="margin-top: 1.5rem; font-size: 1.1rem;">{{ $label }}</h3>
        <div class="comparison" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap: 1rem; margin: 0.75rem 0;">
            <div style="background: var(--bg-subtle); padding: 1rem 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-light);">
                <h4 style="margin: 0 0 0.5rem 0; font-size: 0.9rem; text-transform: uppercase; color: var(--text-muted);">Phiên bản trước</h4>
                @forelse($diff[$key.'_before'] as $r)
                    <p style="margin: 0.35rem 0; font-size: 0.85rem; font-family: var(--font-mono);">
                        {{ $key==='dependencies' ? $r['predecessor'].' → '.$r['successor'].' · chờ '.$r['lag_minutes'].' phút' : $r['task_ref'].' · '.$r['starts_at'].' → '.$r['ends_at'] }}
                    </p>
                @empty
                    <p class="muted" style="font-size: 0.8rem; font-style: italic; margin: 0;">Không có dữ liệu.</p>
                @endforelse
            </div>
            <div style="background: var(--bg-subtle); padding: 1rem 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-light);">
                <h4 style="margin: 0 0 0.5rem 0; font-size: 0.9rem; text-transform: uppercase; color: var(--text-muted);">Đề xuất này</h4>
                @forelse($diff[$key.'_after'] as $r)
                    <p style="margin: 0.35rem 0; font-size: 0.85rem; font-family: var(--font-mono); font-weight: 600;">
                        {{ $key==='dependencies' ? $r['predecessor'].' → '.$r['successor'].' · chờ '.$r['lag_minutes'].' phút' : $r['task_ref'].' · '.$r['starts_at'].' → '.$r['ends_at'] }}
                    </p>
                @empty
                    <p class="muted" style="font-size: 0.8rem; font-style: italic; margin: 0;">Chưa có.</p>
                @endforelse
            </div>
        </div>
    @endforeach
</section>

<!-- Local Auto-scheduling Feature -->
@if($canSchedule)
    <section class="card" style="border-top: 4px solid #8b5cf6;">
        <h2 style="font-size: 1.15rem; margin-bottom: 0.35rem;">⚡ Tự động xếp lịch (Local Schedule)</h2>
        <p class="muted" style="font-size: 0.85rem; margin-bottom: 1rem;">
            Tạo phiên bản mới theo khung giờ làm việc mặc định của hệ thống, tự động né các khung giờ bận cá nhân và bảo toàn thời gian chờ quan hệ FS/lag. Thuật toán chạy nội bộ, chưa áp dụng vào lịch thật.
        </p>
        <form method="post" action="{{ route('workspaces.plans.schedule',[$workspace,$plan]) }}">
            @csrf
            <div style="display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap;">
                <div>
                    <label for="schedule-from">Ngày bắt đầu xếp lịch</label>
                    <input type="date" id="schedule-from" name="from" required value="{{ now($workspace->timezone)->format('Y-m-d') }}" style="max-width: 200px;">
                </div>
                <button type="submit" style="background: #8b5cf6 !important;">
                    Tạo phiên bản xếp lịch tự động
                </button>
            </div>
        </form>
    </section>
@endif

@if(!isset($plan->data['payload']))<p class="notice warning">Bản dữ liệu cũ hoặc fixture chưa có payload chuẩn. Bạn có thể xem dữ liệu tham khảo; hãy tạo kế hoạch mới để áp dụng. Xem trước không xác nhận bản này hợp lệ.</p>@endif
<!-- Advanced JSON Payload Details -->
<details class="card">
    <summary style="font-weight: 700; cursor: pointer;">Dữ liệu JSON Payload chi tiết (Schema V1)</summary>
    <pre style="margin-top: 1rem; padding: 1rem; background: var(--bg-subtle); border-radius: var(--radius-sm); font-family: var(--font-mono); font-size: 0.8rem; overflow: auto; max-height: 400px;">{{ json_encode($plan->data['payload'] ?? $plan->data ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
</details>

@include('plans._impact')
</x-layout>
