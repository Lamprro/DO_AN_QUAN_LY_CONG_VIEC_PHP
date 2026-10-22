@can('update',$task)
@php($quickError = session('task_edit_error.task_id') === $task->id && session('task_edit_error.surface') === 'quick')
<dialog id="quick-edit-modal" class="task-edit-modal" aria-modal="true" aria-labelledby="quick-edit-title" aria-describedby="quick-edit-help" data-reopen="{{ $quickError ? '1' : '0' }}">
 <div class="quick-edit-header"><h2 id="quick-edit-title">Sửa nhanh {{ $task->code }}</h2><button type="button" class="secondary" data-close-quick-edit aria-label="Đóng sửa nhanh">✕</button></div>
 <p id="quick-edit-help" class="muted">Chỉ lưu các trường bên dưới. Đóng cửa sổ sẽ giữ bản nhập trên trang này; tải lại hoặc rời trang sẽ mất bản chưa lưu.</p>
 <form method="post" action="{{ route($routePrefix.'.update',[...$routeParams,$task]) }}" data-form="task-quick-edit" data-dirty-form>
 @csrf @method('PATCH')
 <input type="hidden" name="edit_surface" value="quick">
 <input type="hidden" name="expected_revision" value="{{ $quickError ? old('expected_revision',$task->revision) : $task->revision }}">
 <p class="muted">Bản nhập dựa trên phiên bản #{{ $quickError ? old('expected_revision',$task->revision) : $task->revision }}.</p>
 @if($quickError && $errors->any())
 <div class="notice error" role="alert" tabindex="-1" data-quick-errors><strong>Chưa lưu. Hãy kiểm tra các trường bên dưới.</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
 @endif
 <x-input id="quick_title" name="title" label="Tiêu đề công việc" :value="$task->title" required maxlength="200" />
 <div class="field"><label for="quick_priority">Ưu tiên</label><select id="quick_priority" name="priority">
 @foreach(\App\Enums\TaskPriority::cases() as $priority)<option value="{{ $priority->value }}" @selected(old('priority',$task->priority->value)===$priority->value)>{{ \App\Support\UiLabels::text($priority) }}</option>@endforeach
 </select><div class="priority-selector" aria-label="Chọn nhanh mức ưu tiên">@foreach(\App\Enums\TaskPriority::cases() as $priority)<button type="button" class="priority-btn" data-priority="{{ $priority->value }}">{{ \App\Support\UiLabels::text($priority) }}</button>@endforeach</div></div>
 @if($task->task_type->value==='WORK')
 <div data-edit-field><x-input id="quick_estimate_minutes" name="estimate_minutes" label="Ước lượng (phút)" type="number" :value="$task->estimate_minutes ?? ''" min="0" max="10000000" />
 <div class="field-presets estimate-presets">@foreach([30=>'30 phút',60=>'1 giờ',120=>'2 giờ',240=>'4 giờ',480=>'8 giờ'] as $value=>$label)<button type="button" class="preset-chip" data-set="{{ $value }}">{{ $label }}</button>@endforeach<button type="button" class="preset-chip" data-add="30">+30 phút</button><button type="button" class="preset-chip" data-clear="1">Xóa</button></div></div>
 @endif
 <div data-edit-field><x-input id="quick_deadline_at" name="deadline_at" :label="'Hạn chót — '.$workspace->timezone" type="datetime-local" :value="$task->deadline_at?->setTimezone($workspace->timezone)->format('Y-m-d\TH:i') ?? ''" />@include('tasks._deadline-presets')</div>
 <x-textarea id="quick_deadline_reason" name="deadline_reason" label="Lý do đổi hạn (bắt buộc khi thay đổi hạn chót)" maxlength="2000" />
 @if($canAssign && $task->task_type->value!=='GROUP')
 <div class="quick-edit-grid">@foreach(['assignee_id'=>'Người thực hiện','reviewer_id'=>'Người duyệt'] as $field=>$label)
 @php($selectPeople=$field==='reviewer_id'?$reviewers:$people)
 @php($currentId=$task->getAttribute($field))
 @php($selected=old($field,$currentId))
 <div class="field"><label for="quick_{{ $field }}">{{ $label }}</label><select id="quick_{{ $field }}" name="{{ $field }}"><option value="">Chưa phân công</option>
 @if($currentId && !$selectPeople->contains('user_id',$currentId))<option value="{{ $currentId }}" @selected($selected===$currentId)>Giữ người đã phân công (hiện không đủ điều kiện)</option>@endif
 @if($selected && $selected!==$currentId && !$selectPeople->contains('user_id',$selected))<option value="{{ $selected }}" selected>Lựa chọn đã nhập (cần chọn lại người đủ điều kiện)</option>@endif
 @foreach($selectPeople as $person)<option value="{{ $person->user_id }}" @selected($selected===$person->user_id)>{{ $person->user->name }}</option>@endforeach
 </select>@error($field)<small class="error">{{ $message }}</small>@enderror</div>
 @endforeach</div>
 @endif
 <div class="actions"><button type="button" class="secondary" data-close-quick-edit>Đóng (chưa lưu)</button><button type="submit">Lưu nhanh (Ctrl+↵)</button></div>
 </form>
</dialog>
<noscript><p>Sửa nhanh cần JavaScript. <a href="{{ route($routePrefix.'.edit',[...$routeParams,$task]) }}">Mở form chỉnh sửa đầy đủ</a>.</p></noscript>
@endcan
