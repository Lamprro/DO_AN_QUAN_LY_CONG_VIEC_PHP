<x-layout title="Xem trước áp dụng kế hoạch">
<div style="margin-bottom: 1rem;">
    <a href="{{ route('workspaces.plans.show',[$workspace,$plan]) }}" style="font-size: 0.875rem; font-weight: 600;">
        ‹ Quay lại chi tiết kế hoạch
    </a>
</div>

<section class="card" data-page="plan-preview" style="border-top: 4px solid var(--primary); padding: 1.5rem;">
    <div class="page-heading">
        <div>
            <p class="eyebrow">MÔ PHỎNG ÁP DỤNG · CHƯA GHI ĐÈ DỮ LIỆU THẬT</p>
            <h1 style="margin-bottom: 0.35rem;">{{ $plan->name }} · v{{ $plan->version_no }}</h1>
            <p class="muted" style="margin: 0;">
                Bạn đang xem trước kết quả mô phỏng áp dụng. Hệ thống đã chạy thử nghiệm toàn bộ logic trong transaction rollback, chưa có công việc, lịch trình hay thông báo nào bị ghi đè.
            </p>
        </div>
    </div>

    @if(!$preview['valid'])
        <div class="notice errors" role="alert" style="margin: 1.5rem 0;">
            <div>
                <h2>⚠️ Kế hoạch chưa thể áp dụng do các ràng buộc sau:</h2>
                <ul>
                    @foreach($preview['errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        <p class="muted">Vui lòng kiểm tra lại quyền và dữ liệu nguồn; nếu nguồn đã thay đổi, hãy tạo một phiên bản kế hoạch mới để rà soát.</p>
    @else
        <div class="notice success" style="margin: 1.5rem 0;">
            <span>✓</span>
            <div>Mô phỏng hợp lệ tại thời điểm kiểm tra! Khi áp dụng thật, hệ thống sẽ xác thực lại toàn bộ quyền, snapshot và lịch bận trong transaction an toàn.</div>
        </div>

        @if(!empty($preview['base_active_label']))
            <div style="background: var(--bg-subtle); padding: 0.85rem 1.15rem; border-radius: var(--radius-md); border: 1px solid var(--border-light); margin-bottom: 1.5rem;">
                Phiên bản đang áp dụng hiện tại sẽ được thay thế: <strong>{{ $preview['base_active_label'] }}</strong>
            </div>
        @endif

        <!-- Tasks Impact -->
        <h2 style="font-size: 1.25rem; margin-top: 1.5rem; margin-bottom: 0.5rem;">📋 Công việc sẽ tạo mới hoặc cập nhật</h2>
        <div style="display: flex; flex-direction: column; gap: 0.65rem; margin-bottom: 1.5rem;">
            @foreach($preview['tasks'] as $row)
                <div style="background: var(--bg-subtle); padding: 1rem 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-light); border-left: 4px solid {{ $row['action']==='create' ? 'var(--success)' : 'var(--primary)' }};">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                        <div>
                            <span class="badge" style="{{ $row['action']==='create' ? 'background: #ecfdf5; color: #047857; font-weight: 700;' : 'background: #eff6ff; color: #1d4ed8; font-weight: 700;' }}">
                                {{ $row['action']==='create' ? '＋ Tạo mới' : '🔄 Cập nhật' }}
                            </span>
                            <span style="font-family: var(--font-mono); font-weight: 700; color: var(--primary); margin-left: 0.4rem;">{{ $row['ref'] }}</span>
                            <span style="font-weight: 700; margin-left: 0.4rem;">{{ $row['title'] }}</span>
                        </div>
                    </div>

                    @if($row['changes'])
                        <details class="preview-changes" style="margin-top: 0.65rem; font-size: 0.85rem;">
                            <summary style="cursor: pointer; color: var(--primary); font-weight: 600;">
                                {{ count($row['changes']) }} trường thay đổi so với dữ liệu hiện tại
                            </summary>
                            <dl class="diff-fields" style="background: var(--surface); padding: 0.75rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light); margin-top: 0.5rem;">
                                @foreach($row['changes'] as $field=>$change)
                                    <dt style="font-weight: 600;">
                                        {{ ['title'=>'Tiêu đề','description'=>'Mô tả','task_type'=>'Loại','parent_task_id'=>'Nhóm cha','priority'=>'Ưu tiên','estimate_minutes'=>'Ước lượng (phút)','assignee_id'=>'Người thực hiện','reviewer_id'=>'Người duyệt','sort_order'=>'Thứ tự'][$field] ?? $field }}:
                                    </dt>
                                    <dd style="margin: 0;">
                                        <span class="muted">{{ $change['before'] }}</span>
                                        <span style="margin: 0 0.4rem; color: var(--primary); font-weight: 700;">→</span>
                                        <strong>{{ $change['after'] }}</strong>
                                    </dd>
                                @endforeach
                            </dl>
                        </details>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- Events / Slots Impact -->
        <h2 style="font-size: 1.25rem; margin-bottom: 0.5rem;">📅 Khung giờ mới sẽ tạo trong Lịch</h2>
        <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1.5rem;">
            @forelse($preview['events'] as $event)
                <div style="background: var(--bg-subtle); padding: 0.75rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light); font-size: 0.875rem;">
                    <strong>{{ $event['title'] }}</strong> · <span style="font-family: var(--font-mono);">{{ $event['starts_at'] }} → {{ $event['ends_at'] }}</span> ({{ $event['timezone'] }})
                </div>
            @empty
                <p class="muted" style="font-style: italic;">Không có khung giờ mới nào được tạo.</p>
            @endforelse
        </div>

        <!-- Replaced Events -->
        <h2 style="font-size: 1.25rem; margin-bottom: 0.5rem;">🔄 Lịch tương lai của bản active có thể được thay thế</h2>
        <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1.5rem;">
            @forelse($preview['replaced'] as $event)
                <div style="background: var(--bg-subtle); padding: 0.75rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light); font-size: 0.875rem;">
                    <strong>{{ $event['title'] }}</strong> · <span style="font-family: var(--font-mono);">{{ $event['starts_at'] }} UTC</span>
                </div>
            @empty
                <p class="muted" style="font-style: italic;">Không có khung lịch nào bị thay thế.</p>
            @endforelse
        </div>

        <p class="muted" style="font-size: 0.85rem; margin-bottom: 1.5rem;">
            ℹ️ Các công việc đã bắt đầu, các khung lịch đã qua và lịch có ghi nhận giờ thực tế đều được bảo toàn theo quy tắc.
        </p>

        @if($plan->status->value==='approved')
            <div style="padding-top: 1.25rem; border-top: 1px solid var(--border);">
                <form method="post" action="{{ route('workspaces.plans.action',[$workspace,$plan,'apply']) }}" data-form="plan-apply" data-confirm="Áp dụng thật phiên bản này vào task và lịch?">
                    @csrf
                    <input type="hidden" name="expected_status" value="approved">
                    <button type="submit" style="font-size: 1rem; padding: 0.75rem 1.75rem; background: var(--success) !important;">
                        🚀 Xác nhận áp dụng thật kế hoạch này
                    </button>
                </form>
            </div>
        @else
            <div class="notice warning">
                Đây là kết quả mô phỏng. Hãy quay lại để đề xuất (Propose) và phê duyệt (Approve) trước khi có thể áp dụng chính thức vào dự án.
            </div>
        @endif
    @endif
</section>
</x-layout>
