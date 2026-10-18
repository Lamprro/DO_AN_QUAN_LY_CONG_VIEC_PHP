<x-layout :title="$event?'Sửa sự kiện':'Tạo sự kiện'"><section class="card" data-page="calendar-form"><a href="{{ route('workspaces.calendar.index',$workspace) }}">Về lịch</a><h1>{{ $event?'Sửa sự kiện':'Tạo sự kiện' }}</h1>
@php($allDay = (bool) old('all_day',request('all_day',$event?->all_day ?? false)))
@php($zone = $event?->timezone ?? auth()->user()->timezone)
<p><a href="{{ request()->fullUrlWithQuery(['all_day'=>0]) }}">Nhập giờ cụ thể</a> · <a href="{{ request()->fullUrlWithQuery(['all_day'=>1]) }}">Nhập cả ngày</a></p>
<form method="POST" action="{{ $event?route('workspaces.calendar.update',[$workspace,$event]):route('workspaces.calendar.store',$workspace) }}" data-form="calendar-save" data-dirty-form>
@csrf
@if($event)
@method('PATCH')<input type="hidden" name="version" value="{{ $version }}">
@endif
<input type="hidden" name="all_day" value="{{ $allDay?1:0 }}">
<x-input name="title" label="Tiêu đề" :value="$event?->title ?? ($prefill['title']??'')" required maxlength="200"/>
<x-textarea name="description" label="Nội dung" :value="$event?->description ?? ''"/>
@if($event)<input type="hidden" name="event_type" value="{{ $event->event_type->value }}"><input type="hidden" name="project_id" value="{{ $event->project_id }}"><input type="hidden" name="task_id" value="{{ $event->task_id }}">
@endif
<label for="event_type">Loại</label><select id="event_type" name="event_type"
@disabled($event)>
@foreach(\App\Enums\CalendarEventType::cases() as $type)<option value="{{ $type->value }}"
@selected(old('event_type',$event?->event_type->value ?? ($prefill['event_type']??'NOTE'))===$type->value)>{{ \App\Support\UiLabels::text($type) }}</option>
@endforeach</select>
<label for="project_id">Project (để trống cho lịch workspace)</label><select name="project_id" id="project_id"
@disabled($event)><option value="">Không chọn</option>
@foreach($projects as $project)<option value="{{ $project->id }}"
@selected(old('project_id',$event?->project_id ?? ($prefill['project_id']??null))===$project->id)>{{ $project->name }}</option>
@endforeach</select>
<label for="task_id">WORK được giao cho bạn (chỉ TASK_BLOCK)</label><select name="task_id" id="task_id"
@disabled($event)><option value="">Không chọn</option>
@foreach($tasks as $task)<option value="{{ $task->id }}"
@selected(old('task_id',$event?->task_id ?? ($prefill['task_id']??null))===$task->id)>{{ $task->code }} · {{ $task->title }}</option>
@endforeach</select>
<p class="muted">Chọn task cùng project. Loại, project và task không đổi sau khi tạo. TASK_BLOCK cần WORK đang mở được giao cho bạn.</p>
<label for="timezone">Múi giờ nhập</label><select name="timezone" id="timezone">
@foreach($timezones as $timezone)<option value="{{ $timezone }}"
@selected(old('timezone',$zone)===$timezone)>{{ $timezone }}</option>
@endforeach</select>
<x-input name="starts_at" label="Bắt đầu" :type="$allDay?'date':'datetime-local'" :value="$event?->starts_at->setTimezone($zone)->format($allDay?'Y-m-d':'Y-m-d\TH:i') ?? ''" required/>
<x-input name="ends_at" label="Kết thúc (all-day: ngày cuối không gồm)" :type="$allDay?'date':'datetime-local'" :value="$event?->ends_at->setTimezone($zone)->format($allDay?'Y-m-d':'Y-m-d\TH:i') ?? ''" required/>
<input type="hidden" name="is_busy" value="0"><label><input type="checkbox" name="is_busy" value="1"
@checked(old('is_busy',$event?->is_busy ?? false))> Chiếm giờ (NOTE tùy chọn; MEETING/BUSY/TASK_BLOCK luôn bận; EXTRA_AVAILABLE không bận)</label>
<label for="visibility">Hiển thị</label><select id="visibility" name="visibility">
@foreach(['private'=>'Riêng tư','workspace'=>'Workspace','project'=>'Project'] as $key=>$text)<option value="{{ $key }}"
@selected(old('visibility',$event?->visibility->value ?? 'private')===$key)>{{ $text }}</option>
@endforeach</select>
<label for="status">Trạng thái lịch</label><select id="status" name="status">
@foreach(['planned','completed','cancelled'] as $status)<option value="{{ $status }}"
@selected(old('status',$event?->status->value ?? 'planned')===$status)>{{ \App\Support\UiLabels::text($status) }}</option>
@endforeach</select>
<label for="participants">Người tham gia tham khảo (tối đa 20)</label><select multiple name="participants[]" id="participants">
@php($selected = old('participants',$event?->participants ?? []))
@foreach($members as $member)<option value="{{ $member->user_id }}"
@selected(is_array($selected) && in_array($member->user_id,$selected,true))>{{ $member->user->name }}</option>
@endforeach</select>
<p>Backend kiểm tra lại người tham gia có quyền trong phạm vi. Không tự tạo lịch cho họ, không kiểm tra/giữ chỗ của họ, không cấp quyền bởi danh sách này.</p><button type="submit">Lưu sự kiện</button></form></section></x-layout>
