<article class="calendar-event kind-{{ strtolower($row['type']) }} {{ $row['status']==='cancelled'?'event-cancelled':'' }}" data-calendar-row
@if($row['id']) data-event-id="{{ $row['id'] }}"
@endif>
<strong>
@if($row['id'])<a href="{{ route('workspaces.calendar.show',[$workspace,$row['id']]) }}">{{ $row['title'] }}</a>
@else{{ $row['title'] }}
@endif</strong>
<p><time datetime="{{ $row['starts_at']->toIso8601String() }}">{{ $row['starts_at']->format('d/m H:i') }}</time> → <time datetime="{{ $row['ends_at']->toIso8601String() }}">{{ $row['ends_at']->format('d/m H:i') }}</time>
@if($row['all_day']) · Cả ngày (ngày cuối không gồm)
@endif</p>
<p>
@if($row['owner']){{ $row['owner'] }} ·
@endif{{ \App\Support\UiLabels::text($row['type']) }} · {{ $row['is_busy']?'Bận':'Không chiếm giờ' }}
@if($row['status']) · {{ \App\Support\UiLabels::text($row['status']) }}
@endif</p>
@if($row['task'])<a href="{{ route($row['task']->project_id?'workspaces.projects.tasks.show':'workspaces.tasks.show',$row['task']->project_id?[$workspace,$row['task']->project,$row['task']]:[$workspace,$row['task']]) }}">Mở công việc {{ $row['task']->code }}</a>
@endif</article>
