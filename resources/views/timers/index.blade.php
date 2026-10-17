<x-layout title="Giờ làm thực tế"><section class="card" data-page="timers"><h1>Giờ làm thực tế của tôi</h1><p>Timer ghi nhận thời gian thực tế. Chỉ bắt đầu WORK đang IN_PROGRESS và giao cho bạn. Dừng timer vẫn được phép khi mất quyền xem task.</p>
@foreach($entries as $entry)<article class="card"><p>
@if(\Illuminate\Support\Facades\Gate::allows('view',$entry->task)){{ $entry->task->code }} — {{ $entry->task->title }}
@else Công việc không còn quyền xem
@endif</p><p>{{ $entry->started_at->timezone(auth()->user()->timezone)->format('Y-m-d H:i:s') }} → {{ $entry->ended_at?->timezone(auth()->user()->timezone)->format('Y-m-d H:i:s') ?? 'Đang chạy' }}</p>
@if(!$entry->ended_at)<form method="post" action="{{ route('timers.stop',$entry) }}">
@csrf<button>Dừng timer</button></form>
@else<p>{{ round($entry->started_at->diffInSeconds($entry->ended_at)/60,2) }} phút</p>
@endif</article>
@endforeach{{ $entries->links() }}</section></x-layout>
