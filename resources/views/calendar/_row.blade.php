<article class="card" data-calendar-row @if($row['id']) data-event-id="{{ $row['id'] }}" @endif>
<strong>@if($row['id'])<a href="{{ route('workspaces.calendar.show',[$workspace,$row['id']]) }}">{{ $row['title'] }}</a>@else{{ $row['title'] }}@endif</strong>
<p>{{ $row['starts_at']->format('d/m H:i') }} → {{ $row['ends_at']->format('d/m H:i') }} @if($row['all_day']) · Cả ngày (ngày cuối không gồm) @endif</p>
<p>{{ $row['owner'] }} · {{ $row['type'] }} · {{ $row['is_busy']?'Bận':'Không chiếm giờ' }} @if($row['status']) · {{ $row['status'] }} @endif</p>
@if($row['task'])<a href="{{ route($row['task']->project_id?'workspaces.projects.tasks.show':'workspaces.tasks.show',$row['task']->project_id?[$workspace,$row['task']->project,$row['task']]:[$workspace,$row['task']]) }}">Mở task {{ $row['task']->code }}</a>@endif
</article>
