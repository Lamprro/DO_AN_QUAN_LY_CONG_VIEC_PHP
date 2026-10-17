<section class="card" data-report-metrics data-work-count="{{ $metrics['inventory']['work_count'] }}"><h2>Hiện trạng tại lúc tạo số liệu</h2><p>{{ \Carbon\CarbonImmutable::parse($metrics['generated_at'])->setTimezone($metrics['timezone'])->format('d/m/Y H:i:s') }} · {{ $metrics['timezone'] }}</p>
<p>Phạm vi: {{ $metrics['workspace_name'] }} · {{ $metrics['filters']['project_name'] ?? 'Các project được phép xem' }}</p>
<div class="grid">@foreach($metrics['inventory']['status_counts'] as $status=>$count)<div><strong>{{ $status }}</strong>: <span data-status="{{ $status }}">{{ $count }}</span></div>@endforeach</div>
<p>WORK: {{ $metrics['inventory']['work_count'] }} · Quá hạn: {{ $metrics['inventory']['overdue'] }} · Blocker mở: {{ $metrics['inventory']['open_blockers'] }} trên {{ $metrics['inventory']['blocked_tasks'] }} task.</p>
<p>Ước lượng: {{ $metrics['inventory']['estimate_minutes'] }} phút · Chưa ước lượng: {{ $metrics['inventory']['estimate_unknown'] }} task.</p>
<p>Tiến độ: {{ $metrics['inventory']['progress']['percent'] }}% · {{ $metrics['inventory']['progress']['method']==='estimate'?'Theo ước lượng':'Theo số WORK' }}</p></section>
<section class="card"><h2>Trong kỳ {{ $metrics['period']['from'] }} → {{ $metrics['period']['to'] }} (ngày cuối không gồm)</h2>
<p>WORK hoàn thành: {{ $metrics['period_metrics']['completed_work'] }} · Dự kiến: {{ $metrics['period_metrics']['planned_minutes'] }} phút.</p>
<p>Thực tế: {{ $metrics['period_metrics']['actual_minutes']===null?'Chưa có phiên làm việc đã kết thúc trong kỳ':$metrics['period_metrics']['actual_minutes'].' phút' }}.</p>
<p>Phiên đang chạy không cộng vào actual: {{ $metrics['period_metrics']['running_entries_excluded'] }}.</p></section>
<section class="card"><h2>Khối lượng theo người</h2><div class="table-wrap"><table><thead><tr><th>Thành viên</th><th>WORK không hủy</th><th>Ước lượng (phút)</th><th>Chưa ước lượng</th><th>Dự kiến (phút)</th><th>Thực tế (phút)</th></tr></thead><tbody>
@forelse($metrics['workload'] as $person)<tr data-workload-user="{{ $person['user_id'] }}"><td>{{ $person['name'] }}</td><td>{{ $person['tasks'] }}</td><td>{{ $person['estimate_minutes'] }}</td><td>{{ $person['estimate_unknown'] }}</td><td>{{ $person['planned_minutes'] }}</td><td>{{ $person['actual_minutes']??'Chưa có dữ liệu' }}</td></tr>@empty<tr><td colspan="6">Không có WORK trong phạm vi được xem.</td></tr>@endforelse
</tbody></table></div></section>
<section class="card"><h2>Cách tính và nguồn dữ liệu</h2>@foreach($metrics['definitions'] as $definition)<p>{{ $definition }}</p>@endforeach
<p>Nguồn: {{ count($metrics['sources']['task_ids']) }} task · {{ count($metrics['sources']['calendar_event_ids']) }} khung lịch được phép đọc · {{ count($metrics['sources']['time_entry_ids']) }} phiên đã kết thúc.</p>
<p>Khung giờ mặc định: {{ $metrics['work_schedule']['timezone'] }}, các thứ ISO {{ implode(', ',$metrics['work_schedule']['weekdays']) }}, slot {{ $metrics['work_schedule']['slot_minutes'] }} phút.</p></section>
