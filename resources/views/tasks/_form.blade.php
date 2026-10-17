@if($task)
<input type="hidden" name="expected_revision" value="{{ old('expected_revision',$task->revision) }}">
<p class="muted">Bản dữ liệu #{{ $task->revision }}. <a href="{{ request()->url() }}?fresh=1">Mở dữ liệu hiện tại (bỏ bản nhập chưa lưu)</a></p>
@endif
<div class="field"><label for="task_type">Loại công việc</label><select id="task_type" name="task_type">@foreach(['GROUP'=>'Nhóm công việc','WORK'=>'Công việc','MILESTONE'=>'Cột mốc'] as $value=>$label)<option value="{{ $value }}" @selected(old('task_type',$task?->task_type->value ?? 'WORK')===$value)>{{ $label }}</option>@endforeach</select></div>
<div class="field"><label for="parent_task_id">Nhóm cha</label><select id="parent_task_id" name="parent_task_id"><option value="">Không có</option>@foreach($parents as $parent)@if($parent->id!==$task?->id)<option value="{{ $parent->id }}" @selected(old('parent_task_id',$task?->parent_task_id)===$parent->id)>{{ $parent->code }} — {{ $parent->title }}</option>@endif
@endforeach</select></div>
<x-input name="group_label" label="Nhãn nhóm (tùy chọn)" :value="$task?->group_label ?? ''" maxlength="120" />
<x-input name="sort_order" label="Thứ tự trong nhóm" type="number" :value="$task?->sort_order ?? 0" min="0" max="1000000" />
<p class="muted">Tối đa {{ config('tasks.max_depth') }} cấp. GROUP/MILESTONE không nhập ước lượng; GROUP không phân công trực tiếp.</p>
<x-input name="code" label="Mã task (để trống để tạo tự động)" :value="$task?->code ?? ''" maxlength="40" />
<x-input name="title" label="Tiêu đề" :value="$task?->title ?? ''" required maxlength="200" />
<x-textarea name="description" label="Mô tả" :value="$task?->description ?? ''" maxlength="20000" />
<div class="field"><label for="priority">Ưu tiên</label><select id="priority" name="priority">@foreach(\App\Enums\TaskPriority::cases() as $priority)<option value="{{ $priority->value }}" @selected(old('priority',$task?->priority->value ?? 'medium')===$priority->value)>{{ \App\Support\UiLabels::text($priority) }}</option>@endforeach</select></div>
@if(!$task || $task->task_type->value==='WORK')<x-input name="estimate_minutes" label="Ước lượng (phút)" type="number" :value="$task?->estimate_minutes ?? ''" min="0" max="10000000" />@endif
<x-input name="deadline_at" :label="'Hạn hoàn thành — '.$workspace->timezone" type="datetime-local" :value="$task?->deadline_at?->setTimezone($workspace->timezone)->format('Y-m-d\TH:i') ?? ''" />
@if($canAssign)@foreach(['assignee_id'=>'Người thực hiện','reviewer_id'=>'Người duyệt'] as $field=>$label)<div class="field"><label for="{{ $field }}">{{ $label }}</label><select id="{{ $field }}" name="{{ $field }}"><option value="">Chưa phân công</option>
@php($selectPeople=$field==='reviewer_id'?$reviewers:$people)
@php($currentId=$task?->getAttribute($field))
@if($currentId && !$selectPeople->contains('user_id',$currentId))<option value="{{ $currentId }}" @selected(old($field,$currentId)===$currentId)>Giữ người đã phân công (hiện không còn đủ điều kiện)</option>@endif
@foreach($selectPeople as $person)<option value="{{ $person->user_id }}" @selected(old($field,$currentId)===$person->user_id)>{{ $person->user->name }} — {{ $person->user->email }}</option>@endforeach</select></div>@endforeach
@endif
<p class="muted">Công việc mới bắt đầu ở trạng thái Chưa thực hiện.</p>
<button type="submit">Lưu task</button><a href="{{ route($routePrefix.'.index',$routeParams) }}">Về danh sách</a>
