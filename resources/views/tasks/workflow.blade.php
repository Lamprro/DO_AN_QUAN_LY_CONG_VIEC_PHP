<section class="card"><div class="actions"><a href="{{ route($routePrefix.'.tree',$routeParams) }}">Cây công việc</a><span>Tiến độ WORK lá: {{ $progress['percent'] }}% ({{ $progress['method']==='estimate'?'theo ước lượng':'theo số lượng' }})</span></div>
<h2>Thao tác tiếp theo</h2>
@foreach($workflowUi['notices'] as $notice)<p>{{ $notice }}</p>
@endforeach
@if($workflowUi['actions'])
<form method="post" action="{{ route($routePrefix.'.status.update',[...$routeParams,$task]) }}" data-form="task-status">
@csrf
@method('PATCH')<input type="hidden" name="expected_status" value="{{ $task->status->value }}">
<x-textarea name="reason" id="status-reason" label="Lý do (bắt buộc khi hủy, mở lại hoặc yêu cầu sửa)" maxlength="2000" />
<div class="actions">
@foreach($workflowUi['actions'] as $action)<div><button type="submit" name="status" value="{{ $action['status'] }}" data-reason="{{ $action['needs_reason']?'1':'0' }}"
@disabled(!$action['enabled']) class="{{ $action['status']==='CANCELLED'?'danger':'' }}">{{ $action['label'] }}</button>
@if($action['reason'])<p class="workflow-blocked">{{ $action['reason'] }}</p>
@endif</div>
@endforeach</div></form>
@endif</section>
<section class="card"><h2>Dependency FS</h2>
@forelse($dependencies as $edge)<article><p><a href="{{ route($routePrefix.'.show',[...$routeParams,$edge->predecessor]) }}">{{ $edge->predecessor->code }} — {{ $edge->predecessor->title }}</a> · {{ \App\Support\UiLabels::text($edge->predecessor->status) }} · chờ {{ $edge->lag_minutes }} phút sau hoàn tất</p>
@can('link',$task)<form method="POST" action="{{ route($routePrefix.'.dependencies.destroy',[...$routeParams,$task,$edge->id]) }}" data-form="dependency-delete">
@csrf
@method('DELETE')<button type="submit" class="danger">Gỡ dependency</button></form>
@endcan</article>
@empty<p>Không có dependency.</p>
@endforelse
@can('link',$task)
@if($task->status->value==='TODO' && $task->task_type->value!=='GROUP')<form method="POST" action="{{ route($routePrefix.'.dependencies.store',[...$routeParams,$task]) }}" data-form="dependency-create">
@csrf<label for="predecessor_task_id">Công việc phải hoàn tất trước</label><select name="predecessor_task_id" id="predecessor_task_id" required>
@foreach($candidates as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->code }} — {{ $candidate->title }}</option>
@endforeach</select><x-input name="lag_minutes" label="Thời gian chờ (phút)" type="number" value="0" min="0" max="525600" required /><button type="submit">Thêm dependency</button></form>
@endif
@endcan</section>
<section class="card"><h2>Blocker</h2>
@foreach($blockers as $blocker)<article data-blocker-id="{{ $blocker->id }}"><p>{{ $blocker->reason }} · {{ \App\Support\UiLabels::text($blocker->status->value) }}</p><small>Báo bởi {{ $blocker->reporter?->name }} · {{ $blocker->created_at->setTimezone($workspace->timezone)->format('d/m/Y H:i') }}
@if($blocker->resolved_at) · xử lý bởi {{ $blocker->resolver?->name }}
@endif</small>
@can('resolveBlocker',[$task,$blocker])<form method="POST" action="{{ route($routePrefix.'.blockers.update',[...$routeParams,$task,$blocker->id]) }}" data-form="blocker-update">
@csrf
@method('PATCH')<input type="hidden" name="status" value="{{ $blocker->status->value==='open'?'resolved':'open' }}"><button type="submit">{{ $blocker->status->value==='open'?'Đánh dấu đã xử lý':'Mở lại blocker' }}</button></form>
@endcan</article>
@endforeach
@can('block',$task)
@if(!in_array($task->status->value,['DONE','CANCELLED'],true) && $task->task_type->value!=='GROUP')<form method="POST" action="{{ route($routePrefix.'.blockers.store',[...$routeParams,$task]) }}" data-form="blocker-create">
@csrf<x-textarea name="reason" id="blocker-reason" label="Vấn đề đang chặn công việc" required maxlength="4000" /><button type="submit">Báo blocker</button></form>
@endif
@endcan</section>
<section class="card"><h2>Trao đổi và tệp đính kèm</h2>
@can('comment',$task)<form method="POST" action="{{ route($routePrefix.'.comments.store',[...$routeParams,$task]) }}" data-form="comment-create">
@csrf<x-textarea name="content" label="Bình luận" required maxlength="10000" /><button type="submit">Gửi bình luận</button></form>
@endcan
@can('attach',$task)<form method="POST" action="{{ route($routePrefix.'.attachments.store',[...$routeParams,$task]) }}" enctype="multipart/form-data" data-form="attachment-create">
@csrf<label for="task-file">Tệp đính kèm</label><input type="file" name="file" id="task-file" accept=".pdf,.png,.jpg,.jpeg,.txt,.csv,.docx,.xlsx" required><p class="muted">Tối đa {{ config('tasks.attachment_max_kb') }} KB; PDF, ảnh PNG/JPEG, TXT/CSV, DOCX/XLSX.</p><button type="submit">Tải tệp lên</button></form>
@endcan</section>
<section class="card" data-page="task-activities"><h2>Activity / lịch sử</h2><form method="GET" action="{{ route($routePrefix.'.show',[...$routeParams,$task]) }}"><label for="activity_type">Loại hoạt động</label><select name="activity_type" id="activity_type"><option value="">Tất cả</option>
@foreach(['COMMENT'=>'Bình luận','HISTORY'=>'Lịch sử hệ thống','ATTACHMENT'=>'Tệp đính kèm'] as $value=>$label)<option value="{{ $value }}"
@selected(request('activity_type')===$value)>{{ $label }}</option>
@endforeach</select><button type="submit">Lọc activity</button></form>
@foreach($activities as $activity)<article class="card" data-activity-id="{{ $activity->id }}"><small>{{ $activity->actor?->name ?? 'Hệ thống' }} · {{ $activity->created_at->setTimezone($workspace->timezone)->format('d/m/Y H:i') }} · {{ ['COMMENT'=>'Bình luận','HISTORY'=>'Lịch sử','ATTACHMENT'=>'Tệp đính kèm'][$activity->type->value] }}</small>
@if($activity->type->value==='COMMENT')<p style="white-space:pre-wrap">{{ $activity->content }}</p>
@can('comment',$task)
@if($activity->actor_id===auth()->id())<form method="POST" action="{{ route($routePrefix.'.comments.update',[...$routeParams,$task,$activity->id]) }}" data-form="comment-update">
@csrf
@method('PATCH')<label for="comment-{{ $activity->id }}">Sửa bình luận</label><textarea id="comment-{{ $activity->id }}" name="content" required maxlength="10000">{{ $activity->content }}</textarea><button type="submit">Lưu bình luận</button></form>
@endif
@if($activity->actor_id===auth()->id() || $canAssign)<form method="POST" action="{{ route($routePrefix.'.comments.destroy',[...$routeParams,$task,$activity->id]) }}" data-form="comment-delete">
@csrf
@method('DELETE')<button type="submit" class="danger">Xóa bình luận</button></form>
@endif
@endcan
@elseif($activity->type->value==='ATTACHMENT')<p>{{ $activity->content }}</p>
@if(($activity->data['disk']??null)==='task_files')<a href="{{ route($routePrefix.'.attachments.download',[...$routeParams,$task,$activity->id]) }}">Tải xuống</a>
@can('attach',$task)
@if($activity->actor_id===auth()->id() || $canAssign)<form method="POST" action="{{ route($routePrefix.'.attachments.destroy',[...$routeParams,$task,$activity->id]) }}" data-form="attachment-delete">
@csrf
@method('DELETE')<p class="muted">Gỡ bản ghi đính kèm; nội dung byte được giữ trong kho riêng theo chính sách hiện tại, không còn tải qua bản ghi đã gỡ.</p><button type="submit" class="danger">Gỡ tệp</button></form>
@endif
@endcan
@else<p>Tệp mẫu chưa có nội dung tải xuống.</p>
@endif
@else<p>Lịch sử hệ thống: {{ ['task.deadline_changed'=>'Thay đổi hạn có lý do','timer.corrected'=>'Điều chỉnh phiên giờ có audit','plan.applied'=>'Áp dụng kế hoạch','plan.calendar_replaced'=>'Thay lịch bằng phiên bản mới','timer.started'=>'Bắt đầu giờ làm','timer.stopped'=>'Kết thúc giờ làm','github.commit'=>'Commit GitHub liên quan','task.created'=>'Tạo công việc','task.updated'=>'Cập nhật công việc','task.status_changed'=>'Đổi trạng thái','task.group_synced'=>'Tổng hợp nhóm','task.archived'=>'Lưu trữ','task.restored'=>'Khôi phục','task.dependency_added'=>'Thêm dependency','task.dependency_removed'=>'Gỡ dependency','task.blocker_reported'=>'Báo blocker','task.blocker_updated'=>'Xử lý blocker','task.comment_added'=>'Thêm bình luận','task.comment_updated'=>'Sửa bình luận','task.comment_deleted'=>'Xóa bình luận','task.file_uploaded'=>'Thêm tệp','task.file_removed'=>'Gỡ tệp'][$activity->event_type] ?? 'Sự kiện hệ thống' }}</p>
@if($activity->event_type==='github.commit')<p>{{ $activity->data['commit']??'' }}</p><p style="white-space:pre-wrap">{{ $activity->data['message']??'' }}</p>
@endif
@if($activity->plan_id && app(\App\Services\PlanService::class)->allowed(auth()->user(),$workspace,$project,'plan.view'))<p><a href="{{ route('workspaces.plans.show',[$workspace,$activity->plan_id]) }}">Xem phiên bản kế hoạch</a></p>
@endif
@if(isset($activity->data['from']))<p>{{ \App\Support\UiLabels::text($activity->data['from']) }} → {{ \App\Support\UiLabels::text($activity->data['to']) }}</p>
@endif
@if(!empty($activity->data['reason']))<p>{{ $activity->data['reason'] }}</p>
@endif
@if(isset($activity->data['after']))<dl>
@foreach($activity->data['after'] as $field=>$value)<dt>{{ ['started_at'=>'Bắt đầu (UTC)','ended_at'=>'Kết thúc (UTC)','note'=>'Ghi chú','title'=>'Tiêu đề','description'=>'Mô tả','code'=>'Mã','task_type'=>'Loại','status'=>'Trạng thái','priority'=>'Ưu tiên','assignee_id'=>'Người thực hiện','reviewer_id'=>'Người duyệt','parent_task_id'=>'Nhóm cha','estimate_minutes'=>'Ước lượng (phút)','deadline_at'=>'Hạn','sort_order'=>'Thứ tự','group_label'=>'Nhãn nhóm','revision'=>'Bản dữ liệu'][$field] ?? $field }}</dt><dd>{{ $activityHuman($field,$activity->data['before'][$field]??null) }} → {{ $activityHuman($field,$value) }}</dd>
@endforeach</dl>
@endif
@endif</article>
@endforeach{{ $activities->links() }}</section>
