<section class="card task-journey" aria-labelledby="journey-heading" data-page="task-journey">
    <div class="journey-heading"><div><p class="eyebrow">Luồng xử lý</p><h2 id="journey-heading">Bước tiếp theo của công việc</h2></div><a class="button secondary" href="#task-workflow">Đến phần xử lý trạng thái</a></div>
    @if($task->task_type->value==='GROUP')
        <p>Nhóm tổng hợp trạng thái và tiến độ từ các công việc con. Mở cây để xử lý từng việc.</p>
    @else
        <ol class="workflow-steps" aria-label="Các trạng thái của quy trình">
        @foreach(['TODO'=>'Chuẩn bị','IN_PROGRESS'=>'Thực hiện','REVIEW'=>'Chờ duyệt','DONE'=>'Hoàn thành'] as $status=>$label)
            <li @if($task->status->value===$status) aria-current="step" @endif><span>{{ $loop->iteration }}</span>{{ $label }}@if($task->status->value===$status)<small>Hiện tại</small>@endif</li>
        @endforeach
        </ol>
        @if($task->status->value==='CANCELLED')<p class="notice warning">Công việc đã hủy. Người có quyền có thể mở lại và phải ghi rõ lý do.</p>@endif
    @endif
    @if($task->archived_at)<p class="notice warning">Công việc đã lưu trữ. Khôi phục trước khi cập nhật.</p>@endif
    @foreach($workflowUi['notices'] as $notice)<p class="muted">{{ $notice }}</p>@endforeach
    @if($workflowUi['actions'])
        <ul class="next-actions">
        @foreach($workflowUi['actions'] as $action)
            <li><strong>{{ $action['label'] }}</strong> — {{ $action['enabled'] ? ($action['needs_reason']?'Có thể thực hiện, cần ghi lý do.':'Có thể thực hiện trong phần trạng thái.') : $action['reason'] }}</li>
        @endforeach
        </ul>
    @endif
    <nav class="section-links" aria-label="Các phần của công việc">
        <a href="#task-workflow">Trạng thái</a><a href="#task-blockers">Vấn đề đang chặn ({{ $blockers->filter(fn($b)=>$b->status->value==='open')->count() }})</a><a href="#task-dependencies">Phụ thuộc ({{ $dependencies->count() }})</a><a href="#task-discussion">Trao đổi và tệp</a><a href="#task-activities">Lịch sử</a><a href="#task-time">Giờ thực tế</a>
    </nav>
</section>
