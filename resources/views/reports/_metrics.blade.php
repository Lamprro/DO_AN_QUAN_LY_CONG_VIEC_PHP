@php($inventory=$metrics['inventory'])
@php($period=$metrics['period_metrics'])

<!-- Real-time Inventory Status -->
<section class="card" data-report-metrics data-work-count="{{ $inventory['work_count'] }}" style="border-top: 4px solid var(--primary);">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem;">
        <div>
            <h2 style="font-size: 1.25rem; margin-bottom: 0.25rem;">
                Hiện trạng tại {{ \Carbon\CarbonImmutable::parse($metrics['generated_at'])->setTimezone($metrics['timezone'])->format('d/m/Y H:i:s') }}
            </h2>
            <p class="muted" style="margin: 0; font-size: 0.85rem;">
                Phạm vi: <strong>{{ $metrics['workspace_name'] }}</strong> · {{ $metrics['filters']['project_name']??'Các project được phép xem' }} · Múi giờ: <strong>{{ $metrics['timezone'] }}</strong>
            </p>
        </div>
    </div>

    <!-- 4 KPI High-impact Stat Cards -->
    <div class="kpi-grid">
        @foreach(['WORK đang mở'=>array_sum(array_intersect_key($inventory['status_counts'],array_flip(['TODO','IN_PROGRESS','REVIEW']))),'Quá hạn hiện tại'=>$inventory['overdue'],'Công việc bị chặn'=>$inventory['blocked_tasks'],'Chờ duyệt'=>$inventory['status_counts']['REVIEW']??0] as $label=>$value)
            <article class="kpi" style="{{ $label==='Quá hạn hiện tại' && $value > 0 ? 'border-top-color: var(--danger);' : ($label==='Công việc bị chặn' && $value > 0 ? 'border-top-color: var(--warning);' : '') }}">
                <span>{{ $label }}</span>
                <strong style="{{ $label==='Quá hạn hiện tại' && $value > 0 ? 'color: var(--danger);' : ($label==='Công việc bị chặn' && $value > 0 ? 'color: var(--warning);' : '') }}">{{ $value }}</strong>
            </article>
        @endforeach
    </div>

    <!-- Status Distribution Table -->
    <h3 style="margin-top: 1.5rem; font-size: 1.05rem;">Phân bổ trạng thái công việc (WORK)</h3>
    <div class="table-wrap" style="margin: 0.75rem 0;">
        <table class="metric-table">
            <caption style="text-align: left; padding: 0.5rem 0.75rem; font-size: 0.8rem; color: var(--text-muted);">
                Số lượng và tỷ lệ tại thời điểm tạo số liệu
            </caption>
            <thead>
                <tr>
                    <th>Trạng thái</th>
                    <th style="width: 140px;">Số lượng</th>
                    <th>Tỷ lệ phân bổ</th>
                </tr>
            </thead>
            <tbody>
                @foreach($inventory['status_counts'] as $status=>$count)
                    <tr>
                        <th>
                            <span class="status-pill status-{{ strtolower(str_replace('_','-',$status)) }}">
                                {{ \App\Support\UiLabels::text($status) }}
                            </span>
                        </th>
                        <td data-status="{{ $status }}" style="font-family: var(--font-mono); font-weight: 700;">
                            {{ $count }}
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <span class="metric-bar" aria-hidden="true" style="margin: 0;">
                                    <span style="width:{{ round(100*$count/max(1,$inventory['work_count']),2) }}%"></span>
                                </span>
                                <span style="font-family: var(--font-mono); font-weight: 600; font-size: 0.85rem;">
                                    {{ round(100*$count/max(1,$inventory['work_count']),1) }}%
                                </span>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div style="background: var(--bg-subtle); padding: 1rem 1.25rem; border-radius: var(--radius-md); font-size: 0.875rem; color: var(--text); border: 1px solid var(--border-light); margin-top: 1rem;">
        <p style="margin: 0 0 0.35rem 0;">
            📊 Tổng WORK: <strong>{{ $inventory['work_count'] }}</strong> · Blocker mở: <strong>{{ $inventory['open_blockers'] }}</strong> · Chưa ước lượng: <strong>{{ $inventory['estimate_unknown'] }}</strong> công việc.
        </p>
        <p style="margin: 0;">
            ⏱️ Ước lượng toàn bộ: <strong>{{ \App\Support\MetricDisplay::minutes($inventory['estimate_minutes']) }}</strong> · Tiến độ tổng thể: <strong>{{ $inventory['progress']['percent'] }}%</strong> ({{ $inventory['progress']['method']==='estimate'?'Theo ước lượng':'Theo số lượng WORK' }}).
        </p>
    </div>
</section>

<!-- Period Metrics & Planned vs Actual -->
<section class="card" style="border-top: 4px solid #0284c7;">
    <h2 style="font-size: 1.25rem; margin-bottom: 0.25rem;">
        Kết quả trong khoảng {{ $metrics['period']['from'] }} → {{ $metrics['period']['to'] }}
    </h2>
    <p class="muted" style="margin: 0 0 1rem 0; font-size: 0.85rem;">
        (Không gồm ngày cuối) · WORK hoàn thành trong kỳ: <strong style="color: var(--success); font-size: 1rem;">{{ $period['completed_work'] }}</strong>. Không dùng trạng thái hiện tại để suy ngược lịch sử.
    </p>

    @php($scale=max(1,$period['planned_minutes'],$period['actual_minutes']??0))
    <div class="table-wrap" style="margin: 1rem 0;">
        <table class="metric-table" data-planned-actual>
            <caption style="text-align: left; padding: 0.5rem 0.75rem; font-size: 0.8rem; color: var(--text-muted);">
                Giờ dự kiến và thực tế trong kỳ — cùng đơn vị phút ở nguồn
            </caption>
            <thead>
                <tr>
                    <th>Nguồn thời gian</th>
                    <th style="width: 180px;">Thời lượng</th>
                    <th>So sánh tương quan</th>
                </tr>
            </thead>
            <tbody>
                @foreach(['planned_minutes'=>'Dự kiến từ lịch','actual_minutes'=>'Thực tế từ phiên đã kết thúc'] as $key=>$label)
                    <tr>
                        <th style="font-size: 0.875rem;">{{ $label }}</th>
                        <td data-minutes="{{ $period[$key]===null?'null':$period[$key] }}" style="font-family: var(--font-mono); font-weight: 700;">
                            {{ \App\Support\MetricDisplay::minutes($period[$key]) }}
                        </td>
                        <td>
                            @if($period[$key]!==null)
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <span class="metric-bar" aria-hidden="true" style="margin: 0; width: 180px;">
                                        <span style="width:{{ round(100*$period[$key]/$scale,2) }}%; {{ $key==='actual_minutes' ? 'background: linear-gradient(90deg, #10b981, #059669);' : '' }}"></span>
                                    </span>
                                    <span style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-muted);">
                                        {{ round(100*$period[$key]/$scale,1) }}%
                                    </span>
                                </div>
                            @else
                                <span class="muted" style="font-style: italic;">Chưa có phiên đã kết thúc</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="muted" style="font-size: 0.8rem; margin: 0.5rem 0 0 0;">
        ℹ️ Phiên đang chạy không cộng vào actual: {{ $period['running_entries_excluded'] }}. Thời lượng hiển thị làm tròn đến giây; số liệu nguồn/CSV vẫn là phút gốc, giá trị null khác giá trị 0.
    </p>
</section>

<!-- Workload By Member -->
<section class="card" style="border-top: 4px solid #8b5cf6;">
    <h2 style="font-size: 1.25rem; margin-bottom: 0.25rem;">Khối lượng công việc theo Thành viên</h2>
    <p class="muted" style="margin: 0 0 1rem 0; font-size: 0.85rem;">Phân tích số lượng và thời gian thực hiện của từng cá nhân trong đội ngũ.</p>

    <div class="table-wrap mobile-data-table">
        <table>
            <thead>
                <tr>
                    <th>Thành viên</th>
                    <th>WORK không hủy</th>
                    <th>Ước lượng</th>
                    <th>Chưa ước lượng</th>
                    <th>Dự kiến trong kỳ</th>
                    <th>Thực tế trong kỳ</th>
                </tr>
            </thead>
            <tbody>
                @forelse($metrics['workload'] as $person)
                    <tr data-workload-user="{{ $person['user_id'] }}">
                        <td data-label="Thành viên">
                            <strong style="color: var(--text);">{{ $person['name'] }}</strong>
                        </td>
                        <td data-label="WORK không hủy">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <strong style="font-family: var(--font-mono);">{{ $person['tasks'] }}</strong>
                                <meter min="0" max="{{ max(1,collect($metrics['workload'])->max('tasks')) }}" value="{{ $person['tasks'] }}" aria-label="{{ $person['name'].' · '.$person['tasks'].' công việc' }}" style="flex: 1; min-width: 80px;"></meter>
                            </div>
                        </td>
                        <td data-label="Ước lượng" style="font-family: var(--font-mono);">
                            {{ \App\Support\MetricDisplay::minutes($person['estimate_minutes']) }}
                        </td>
                        <td data-label="Chưa ước lượng" style="font-family: var(--font-mono); color: {{ $person['estimate_unknown'] > 0 ? 'var(--warning)' : 'inherit' }};">
                            {{ $person['estimate_unknown'] }}
                        </td>
                        <td data-label="Dự kiến" style="font-family: var(--font-mono);">
                            {{ \App\Support\MetricDisplay::minutes($person['planned_minutes']) }}
                        </td>
                        <td data-label="Thực tế" style="font-family: var(--font-mono); font-weight: 700; color: var(--primary);">
                            {{ \App\Support\MetricDisplay::minutes($person['actual_minutes']) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2rem;">
                            <p class="muted">Không có công việc WORK nào trong phạm vi được xem.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<!-- Definitions & Source Attribution -->
<details class="card">
    <summary style="font-weight: 700; cursor: pointer;">Cách tính và Nguồn dữ liệu</summary>
    <div style="margin-top: 0.75rem; font-size: 0.85rem; color: var(--text-muted);">
        @foreach($metrics['definitions'] as $definition)
            <p style="margin: 0.35rem 0;">• {{ $definition }}</p>
        @endforeach
        <div style="padding-top: 0.75rem; border-top: 1px solid var(--border-light); margin-top: 0.75rem;">
            <p style="margin: 0.25rem 0;">📌 Nguồn: <strong>{{ count($metrics['sources']['task_ids']) }}</strong> task · <strong>{{ count($metrics['sources']['calendar_event_ids']) }}</strong> khung lịch · <strong>{{ count($metrics['sources']['time_entry_ids']) }}</strong> phiên giờ đã kết thúc.</p>
            <p style="margin: 0.25rem 0;">⏰ Khung giờ mặc định: <strong>{{ $metrics['work_schedule']['timezone'] }}</strong>, các thứ ISO: <strong>{{ implode(', ',$metrics['work_schedule']['weekdays']) }}</strong>, slot: <strong>{{ $metrics['work_schedule']['slot_minutes'] }}</strong> phút.</p>
        </div>
    </div>
</details>
