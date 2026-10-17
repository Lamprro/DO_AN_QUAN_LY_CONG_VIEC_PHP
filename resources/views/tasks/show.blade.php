<x-layout :title="$task->title"><section class="card" data-page="task-show" data-task-id="{{ $task->id }}"><a href="{{ route($routePrefix.'.index',$routeParams) }}">Danh sách công việc</a><h1>{{ $task->code }} — {{ $task->title }}</h1>
<p>{{ \App\Support\UiLabels::text($task->task_type->value) }} · {{ \App\Support\UiLabels::text($task->status->value) }} · {{ \App\Support\UiLabels::text($task->priority->value) }}
@if($task->archived_at) · Đã lưu trữ
@endif</p>
<p style="white-space:pre-wrap">{{ $task->description }}</p><p>Người tạo: {{ $task->creator->name }}</p><p>Thực hiện: {{ $task->assignee?->name ?? 'Chưa phân công' }} · Duyệt: {{ $task->reviewer?->name ?? 'Chưa phân công' }}</p>
<p>Ước lượng: {{ $task->estimate_minutes ?? 'Chưa có' }} phút · Hạn: {{ $task->deadline_at?->setTimezone($workspace->timezone)->format('d/m/Y H:i') ?? 'Chưa có' }} ({{ $workspace->timezone }})</p>
@can('update',$task)<a class="button" href="{{ route($routePrefix.'.edit',[...$routeParams,$task]) }}">Sửa task</a>
@endcan</section>
@can('archive',$task)<section class="card"><form method="POST" action="{{ route($routePrefix.($task->archived_at?'.restore':'.archive'),[...$routeParams,$task]) }}" data-form="task-archive" data-confirm="Xác nhận thay đổi lưu trữ task này?">
@csrf<button type="submit">{{ $task->archived_at?'Khôi phục':'Lưu trữ task' }}</button></form></section>
@endcan
@can('delete',$task)
@if($deletable)<section class="card"><p>Chỉ xóa task TODO chưa có lịch sử hoặc dữ liệu liên quan.</p><form method="POST" action="{{ route($routePrefix.'.destroy',[...$routeParams,$task]) }}" data-form="task-delete" data-confirm="Xóa vĩnh viễn task nháp này?">
@csrf
@method('DELETE')<button class="danger" type="submit">Xóa task nháp</button></form></section>
@endif
@endcan
@include('tasks.workflow')
<section class="card"><h2>Thời gian thực tế</h2><a href="{{ route('timers.index') }}">Timer của tôi</a>
@if($task->task_type->value==='WORK' && $task->status->value==='IN_PROGRESS' && $task->assignee_id===auth()->id() && auth()->user()->can('execute',$task))<form method="post" action="{{ route('workspaces.timer.start',[$workspace,$task]) }}">
@csrf<button>Bắt đầu timer</button></form>
@endif</section></x-layout>
