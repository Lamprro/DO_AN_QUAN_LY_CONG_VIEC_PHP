<x-layout title="Lịch"><section class="card" data-page="calendar-index"><a href="{{ route('workspaces.show',$workspace) }}">{{ $workspace->name }}</a><h1>Lịch</h1>@if($canCreate)<a class="button" href="{{ route('workspaces.calendar.create',$workspace) }}">Tạo sự kiện</a>@endif
@include('calendar._range')<p>Lịch riêng của người khác chỉ hiện khoảng bận. Người tham gia là danh sách tham khảo, không tự đặt chỗ hay cấp quyền xem.</p></section>
@if($range['mode']==='list')
@forelse($rows as $row) @include('calendar._row') @empty<section class="card">Không có sự kiện trong phạm vi.</section>@endforelse
@else
<div class="calendar-days" data-calendar-mode="{{ $range['mode'] }}">@foreach($range['days'] as $day)<section class="card"><h2>{{ $day }}</h2>
@php($dayRows = collect($rows)->filter(fn($row)=>$row['starts_at']->format('Y-m-d') <= $day && $row['ends_at']->gt(\Carbon\CarbonImmutable::parse($day,$range['timezone']))))
@forelse($dayRows as $row) @include('calendar._row') @empty<p class="muted">Không có sự kiện.</p>@endforelse</section>@endforeach</div>
@endif</x-layout>
