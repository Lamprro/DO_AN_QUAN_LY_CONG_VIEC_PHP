@php($taskParams = $task ? [...$routeParams,$task] : $routeParams)
<section data-panel-content data-panel-mode="{{ $panelMode }}">
<p class="eyebrow">{{ $project?->name ?? $workspace->name }} @if($task) · {{ $task->code }} @endif</p>
<h2 id="task-panel-title">{{ ['create'=>'Tạo công việc','edit'=>'Chỉnh sửa công việc','status'=>'Cập nhật trạng thái','view'=>$task?->title][$panelMode] }}</h2>
@if(in_array($panelMode,['create','edit']))
<p class="muted">Lưu ngay tại đây. Danh sách sẽ cập nhật và giữ bộ lọc bạn đang xem.</p>
<form method="post" action="{{ route($routePrefix.($task?'.update':'.store'),$taskParams) }}" data-panel-form data-form="task-panel-edit">
@csrf @if($task) @method('PATCH')<input type="hidden" name="expected_revision" value="{{ $task->revision }}"><input type="hidden" name="edit_surface" value="quick"> @endif
<div class="field"><label for="panel-title">Tên công việc *</label><input id="panel-title" name="title" value="{{ $task?->title }}" required maxlength="200" autofocus placeholder="Việc cần hoàn thành là gì?"></div>
<div class="field"><label for="panel-description">Mô tả</label><textarea id="panel-description" name="description" rows="3" maxlength="20000" placeholder="Mục tiêu, kết quả mong đợi…">{{ $task?->description }}</textarea></div>
<div class="quick-edit-grid"><div class="field"><label for="panel-priority">Ưu tiên</label><select id="panel-priority" name="priority">@foreach(\App\Enums\TaskPriority::cases() as $p)<option value="{{ $p->value }}" @selected(($task?->priority->value??'medium')===$p->value)>{{ \App\Support\UiLabels::text($p) }}</option>@endforeach</select></div>
@if(!$task || $task->task_type->value==='WORK')<div class="field" data-edit-field><label for="panel-estimate">Ước lượng (phút)</label><input id="panel-estimate" name="estimate_minutes" type="number" min="0" max="10000000" value="{{ $task?->estimate_minutes }}"><div class="estimate-presets field-presets">@foreach([30=>'30 phút',60=>'1 giờ',120=>'2 giờ'] as $v=>$label)<button type="button" class="preset-chip" data-set="{{ $v }}">{{ $label }}</button>@endforeach</div></div>@endif</div>
<div class="field" data-edit-field><label for="panel-deadline">Hạn hoàn thành · {{ $workspace->timezone }}</label><input id="panel-deadline" name="deadline_at" type="datetime-local" value="{{ $task?->deadline_at?->setTimezone($workspace->timezone)->format('Y-m-d\TH:i') }}">@include('tasks._deadline-presets')</div>
@if($task)<div class="field"><label for="panel-deadline-reason">Lý do đổi hạn (khi thay đổi hạn)</label><textarea id="panel-deadline-reason" name="deadline_reason" maxlength="2000" rows="2"></textarea></div>@endif
@if($canAssign && (!$task || $task->task_type->value!=='GROUP'))<div class="quick-edit-grid">@foreach(['assignee_id'=>'Người thực hiện','reviewer_id'=>'Người duyệt'] as $field=>$label)
@php($options=$field==='reviewer_id'?$reviewers:$people) @php($current=$task?->getAttribute($field))
<div class="field"><label for="panel-{{ $field }}">{{ $label }}</label><select id="panel-{{ $field }}" name="{{ $field }}"><option value="">Chưa phân công</option>@if($current && !$options->contains('user_id',$current))<option value="{{ $current }}" selected>Giữ người đã phân công (không còn đủ điều kiện)</option>@endif @foreach($options as $m)<option value="{{ $m->user_id }}" @selected($current===$m->user_id)>{{ $m->user->name }}</option>@endforeach</select></div>
@endforeach</div>@endif
@if(!$task)<details class="panel-options"><summary>Loại công việc, nhóm cha và mã</summary><div class="quick-edit-grid"><div class="field"><label for="panel-type">Loại công việc</label><select id="panel-type" name="task_type"><option value="WORK">Công việc</option><option value="GROUP">Nhóm công việc</option><option value="MILESTONE">Cột mốc</option></select></div><div class="field"><label for="panel-parent">Nhóm cha</label><select id="panel-parent" name="parent_task_id"><option value="">Không có</option>@foreach($parents as $p)<option value="{{ $p->id }}">{{ $p->title }}</option>@endforeach</select></div></div><div class="field"><label for="panel-code">Mã (để trống để tự tạo)</label><input id="panel-code" name="code" maxlength="40"></div></details>@endif
<div class="panel-form-actions"><button type="button" class="secondary" data-panel-close>Đóng</button><button type="submit">{{ $task?'Lưu thay đổi':'Tạo công việc' }}</button></div>
</form>
@elseif($panelMode==='status')
<p>{{ $task->title }}</p><p>Hiện tại: <x-status :value="$task->status" /></p>
<form method="post" action="{{ route($routePrefix.'.status.update',$taskParams) }}" data-panel-form data-form="task-status">
@csrf @method('PATCH')<input type="hidden" name="expected_status" value="{{ $task->status->value }}">
@foreach($workflowUi['notices'] as $notice)<p class="notice">{{ $notice }}</p>@endforeach
<div class="field"><label for="panel-reason">Lý do (bắt buộc khi hủy, mở lại hoặc yêu cầu sửa)</label><textarea id="panel-reason" name="reason" rows="3" maxlength="2000"></textarea></div>
<div class="panel-status-actions">@foreach($workflowUi['actions'] as $action)<div><button type="submit" name="status" value="{{ $action['status'] }}" data-reason="{{ $action['needs_reason']?'1':'0' }}" @disabled(!$action['enabled'])>{{ $action['label'] }}</button>@if($action['reason'])<p class="muted">{{ $action['reason'] }}</p>@endif</div>@endforeach</div>
</form>
@else
<div class="panel-meta"><x-status :value="$task->status"/><x-status :value="$task->priority"/></div>
<p class="panel-description">{{ $task->description ?: 'Chưa có mô tả.' }}</p>
<dl class="panel-facts"><div><dt>Người thực hiện</dt><dd>{{ $task->assignee?->name??'Chưa phân công' }}</dd></div><div><dt>Người duyệt</dt><dd>{{ $task->reviewer?->name??'Chưa phân công' }}</dd></div><div><dt>Hạn hoàn thành</dt><dd>{{ $task->deadline_at?->setTimezone($workspace->timezone)->format('d/m/Y H:i')??'Chưa đặt hạn' }} · {{ $workspace->timezone }}</dd></div></dl>
<div class="actions">@can('update',$task)<a class="button" data-task-panel href="{{ route($routePrefix.'.edit',$taskParams) }}">Chỉnh sửa</a>@endcan @if(count($workflowUi['actions']))<a class="button secondary" data-task-panel href="{{ route($routePrefix.'.status.edit',$taskParams) }}">Đổi trạng thái</a>@endif</div>
<h3>Trao đổi gần đây</h3>@forelse($recentComments as $comment)<article class="panel-comment"><strong>{{ $comment->actor?->name??'Thành viên' }}</strong><p>{{ $comment->content }}</p></article>@empty<p class="muted">Chưa có bình luận. Mở chi tiết để trao đổi, thêm tệp và xem toàn bộ hoạt động.</p>@endforelse
@endif
<p class="panel-full-link"><a href="{{ route($routePrefix.($task?'.show':'.create'),$taskParams) }}">Mở trang đầy đủ ↗</a></p>
</section>
