
@php($inventory=$metrics['inventory'])
@php($period=$metrics['period_metrics'])
<section class="card" data-report-metrics data-work-count="{{ $inventory['work_count'] }}"><h2>Hiện trạng tại {{ \Carbon\CarbonImmutable::parse($metrics['generated_at'])->setTimezone($metrics['timezone'])->format('d/m/Y H:i:s') }}</h2><p>{{ $metrics['workspace_name'] }} · {{ $metrics['filters']['project_name']??'Các project được phép xem' }} · {{ $metrics['timezone'] }}</p>
<div class="kpi-grid">
@foreach(['WORK đang mở'=>array_sum(array_intersect_key($inventory['status_counts'],array_flip(['TODO','IN_PROGRESS','REVIEW']))),'Quá hạn hiện tại'=>$inventory['overdue'],'Công việc bị chặn'=>$inventory['blocked_tasks'],'Chờ duyệt'=>$inventory['status_counts']['REVIEW']??0] as $label=>$value)<article class="kpi"><span>{{ $label }}</span><strong>{{ $value }}</strong></article>
@endforeach</div>
<h3>Phân bổ trạng thái WORK</h3><table class="metric-table"><caption>Số lượng và tỷ lệ tại thời điểm tạo số liệu</caption><thead><tr><th>Trạng thái</th><th>Số lượng</th><th>Tỷ lệ</th></tr></thead><tbody>
@foreach($inventory['status_counts'] as $status=>$count)<tr><th>{{ \App\Support\UiLabels::text($status) }}</th><td data-status="{{ $status }}">{{ $count }}</td><td><span class="metric-bar" aria-hidden="true"><span style="width:{{ round(100*$count/max(1,$inventory['work_count']),2) }}%"></span></span>{{ round(100*$count/max(1,$inventory['work_count']),1) }}%</td></tr>
@endforeach</tbody></table>
<p>Tổng WORK: {{ $inventory['work_count'] }} · Blocker mở: {{ $inventory['open_blockers'] }} · Chưa ước lượng: {{ $inventory['estimate_unknown'] }} công việc.</p><p>Ước lượng toàn bộ: {{ \App\Support\MetricDisplay::minutes($inventory['estimate_minutes']) }}. Tiến độ: {{ $inventory['progress']['percent'] }}% · {{ $inventory['progress']['method']==='estimate'?'Theo ước lượng':'Theo số WORK' }}.</p></section>
<section class="card"><h2>Kết quả trong khoảng {{ $metrics['period']['from'] }} → {{ $metrics['period']['to'] }} (không gồm ngày cuối)</h2><p>WORK hoàn thành trong kỳ: <strong>{{ $period['completed_work'] }}</strong>. Không dùng trạng thái hiện tại để suy ngược lịch sử.</p>
@php($scale=max(1,$period['planned_minutes'],$period['actual_minutes']??0))
<table class="metric-table" data-planned-actual><caption>Giờ dự kiến và thực tế trong kỳ — cùng đơn vị phút ở nguồn</caption><thead><tr><th>Nguồn</th><th>Thời lượng</th><th>So sánh</th></tr></thead><tbody>
@foreach(['planned_minutes'=>'Dự kiến từ lịch','actual_minutes'=>'Thực tế từ phiên đã kết thúc'] as $key=>$label)<tr><th>{{ $label }}</th><td data-minutes="{{ $period[$key]===null?'null':$period[$key] }}">{{ \App\Support\MetricDisplay::minutes($period[$key]) }}</td><td>
@if($period[$key]!==null)<span class="metric-bar" aria-hidden="true"><span style="width:{{ round(100*$period[$key]/$scale,2) }}%"></span></span>
@else Chưa có phiên đã kết thúc
@endif</td></tr>
@endforeach</tbody></table><p>Phiên đang chạy không cộng actual: {{ $period['running_entries_excluded'] }}. Thời lượng hiển thị làm tròn đến giây; số liệu nguồn/CSV vẫn là phút, null khác giá trị0.</p></section>
<section class="card"><h2>Khối lượng theo người</h2><div class="table-wrap mobile-data-table"><table><thead><tr><th>Thành viên</th><th>WORK không hủy</th><th>Ước lượng</th><th>Chưa ước lượng</th><th>Dự kiến trong kỳ</th><th>Thực tế trong kỳ</th></tr></thead><tbody>
@forelse($metrics['workload'] as $person)<tr data-workload-user="{{ $person['user_id'] }}"><td data-label="Thành viên">{{ $person['name'] }}</td><td data-label="WORK không hủy">{{ $person['tasks'] }}<meter min="0" max="{{ max(1,collect($metrics['workload'])->max('tasks')) }}" value="{{ $person['tasks'] }}" aria-label="{{ $person['name'].' · '.$person['tasks'].' công việc' }}">{{ $person['tasks'] }}</meter></td><td data-label="Ước lượng">{{ \App\Support\MetricDisplay::minutes($person['estimate_minutes']) }}</td><td data-label="Chưa ước lượng">{{ $person['estimate_unknown'] }}</td><td data-label="Dự kiến">{{ \App\Support\MetricDisplay::minutes($person['planned_minutes']) }}</td><td data-label="Thực tế">{{ \App\Support\MetricDisplay::minutes($person['actual_minutes']) }}</td></tr>
@empty<tr><td colspan="6">Không có WORK trong phạm vi được xem.</td></tr>
@endforelse</tbody></table></div></section>
<details class="card"><summary>Cách tính và nguồn dữ liệu</summary>
@foreach($metrics['definitions'] as $definition)<p>{{ $definition }}</p>
@endforeach<p>Nguồn: {{ count($metrics['sources']['task_ids']) }} task · {{ count($metrics['sources']['calendar_event_ids']) }} khung lịch · {{ count($metrics['sources']['time_entry_ids']) }} phiên đã kết thúc.</p><p>Khung giờ mặc định: {{ $metrics['work_schedule']['timezone'] }}, các thứ ISO {{ implode(', ',$metrics['work_schedule']['weekdays']) }}, slot {{ $metrics['work_schedule']['slot_minutes'] }} phút.</p></details>
