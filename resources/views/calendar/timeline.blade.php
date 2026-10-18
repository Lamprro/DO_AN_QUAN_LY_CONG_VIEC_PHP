<x-layout title="Timeline project"><section class="card" data-page="project-timeline"><h1>Timeline — {{ $project->name }}</h1><a href="{{ route('workspaces.calendar.index',[$workspace,'project_id'=>$project->id]) }}">Mở lịch project</a>
@include('calendar._range')<p>Thanh là TASK_BLOCK thật, được cắt tại biên phạm vi. Giờ hiển thị theo {{ $range['timezone'] }}; đây là dự kiến, không phải giờ thực tế. Thanh đã hủy có gạch chéo. Danh sách dưới mỗi hàng là cách đọc thay thế.</p></section>
<section class="card"><div class="timeline-scroll" role="region" aria-label="Trục thời gian có thể cuộn ngang" tabindex="0"><div class="timeline-canvas" style="min-width:{{ max(720,count($range['days'])*70) }}px">
<div class="timeline-axis">
@foreach($navigation['ticks'] as $tick)<span style="left:{{ $tick['offset'] }}%">{{ $tick['label'] }}</span>
@endforeach</div>
@forelse($groups as $group)<div class="timeline-group"><h2>{{ $group->first()['task']?->code }} · {{ $group->first()['task']?->title ?? 'Khung lịch được phép xem' }}</h2><div class="timeline-lanes">
@foreach($group as $row)<div class="timeline-slot-track"><a class="timeline-slot {{ $row['status']==='cancelled'?'event-cancelled':'' }}" href="{{ route('workspaces.calendar.show',[$workspace,$row['id']]) }}" style="left:{{ $row['offset_percent'] }}%;width:{{ $row['width_percent'] }}%" aria-label="{{ $row['title'].' · '.$row['starts_at']->format('d/m H:i').' → '.$row['ends_at']->format('d/m H:i').' · '.\App\Support\UiLabels::text($row['status']) }}"><span class="visually-hidden">{{ $row['title'] }}</span></a></div>
@endforeach
@if($navigation['nowPercent']!==null)<span class="timeline-now" style="left:{{ $navigation['nowPercent'] }}%" title="Thời điểm tải trang"><span>Hiện tại</span></span>
@endif</div></div>
@empty<p>Không có TASK_BLOCK được phép xem trong phạm vi.</p>
@endforelse</div></div></section>
<section class="card"><h2>Danh sách tương đương</h2>
@forelse($groups as $group)
@foreach($group as $row)
@include('calendar._row')
@endforeach
@empty<p>Chưa có khung công việc trong phạm vi.</p>
@endforelse</section></x-layout>
