<x-layout title="Lịch"><section class="card" data-page="calendar-index"><h1>Lịch {{ $project?'— '.$project->name:'' }}</h1><p>{{ $project?'Chỉ sự kiện thuộc project này, theo quyền hiện tại.':'Các sự kiện trong workspace được phép xem.' }}</p>
@if($canCreate)<a class="button" href="{{ route('workspaces.calendar.create',[$workspace,'project_id'=>$project?->id]) }}">Tạo sự kiện</a>
@endif
@include('calendar._range')<p>Khoảng giờ bận riêng tư không có liên kết hay nội dung chi tiết. Người tham gia tham khảo không tự được đặt chỗ hoặc cấp quyền.</p></section>
@if($range['mode']==='list')
@forelse($rows as $row)
@include('calendar._row')
@empty<section class="card">Không có sự kiện trong phạm vi ngày và quyền hiện tại.</section>
@endforelse
@else
<div class="calendar-grid-scroll" role="region" aria-label="Lưới lịch theo ngày" tabindex="0"><div class="calendar-grid calendar-mode-{{ $range['mode'] }}">
@if($range['mode']==='month')
@foreach(['Thứ 2','Thứ 3','Thứ 4','Thứ 5','Thứ 6','Thứ 7','Chủ nhật'] as $label)<div class="weekday-label">{{ $label }}</div>
@endforeach
@for($pad=1;$pad<$range['start']->dayOfWeekIso;$pad++)<div class="calendar-empty" aria-hidden="true"></div>
@endfor
@endif
@foreach($range['days'] as $day)<section class="card calendar-day {{ $day===$navigation['todayDate']?'is-today':'' }}" data-day="{{ $day }}"><h2><a href="{{ request()->fullUrlWithQuery(['view'=>'day','date'=>$day,'from'=>null,'to'=>null]) }}">{{ \Carbon\CarbonImmutable::parse($day)->format('d/m/Y') }}</a> {{ $day===$navigation['todayDate']?'· Hôm nay':'' }}</h2>
@php($dayRows=collect($rows)->filter(fn($row)=>$row['starts_at']->lt(\Carbon\CarbonImmutable::parse($day,$range['timezone'])->addDay())&&$row['ends_at']->gt(\Carbon\CarbonImmutable::parse($day,$range['timezone']))))
@forelse($dayRows as $row)
@include('calendar._row')
@empty<p class="muted">Không có sự kiện.</p>
@endforelse</section>
@endforeach</div></div>
@endif</x-layout>
