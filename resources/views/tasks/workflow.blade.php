<!-- Workflow State Machine & Leaf Progress -->
<section class="card" style="border-top: 4px solid var(--primary);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem;">
        <div>
            <h2 style="font-size: 1.25rem; margin: 0;">Trạng thái & Tiến độ công việc</h2>
            <p class="muted" style="margin: 0.25rem 0 0 0; font-size: 0.85rem;">
                Tiến độ WORK lá: <strong>{{ $progress['percent'] }}%</strong> ({{ $progress['method']==='estimate'?'tính theo giờ ước lượng':'tính theo số lượng công việc' }})
            </p>
        </div>
        <div class="actions" style="margin: 0;">
            <a href="{{ route($routePrefix.'.tree',$routeParams) }}" class="button secondary" style="font-size: 0.825rem;">
                🌳 Cây công việc
            </a>
        </div>
    </div>

    <!-- Leaf Task Progress Bar -->
    <div class="timeline-track" style="height: 12px; margin-bottom: 1.25rem;">
        <div class="timeline-bar" style="width: {{ $progress['percent'] }}%; background: linear-gradient(90deg, #10b981, #059669);"></div>
    </div>

    @foreach($workflowUi['notices'] as $notice)
        <div class="notice warning" style="margin: 0.75rem 0;">
            <span>⚠️</span>
            <div>{{ $notice }}</div>
        </div>
    @endforeach

    @if($workflowUi['actions'])
        <div style="background: var(--bg-subtle); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-light); margin-top: 1rem;">
            <h3 style="font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 0.75rem;">
                Chuyển trạng thái tiếp theo
            </h3>

            <form method="post" action="{{ route($routePrefix.'.status.update',[...$routeParams,$task]) }}" data-form="task-status">
                @csrf
                @method('PATCH')
                <input type="hidden" name="expected_status" value="{{ $task->status->value }}">

                <div style="margin-bottom: 1rem;">
                    <x-textarea name="reason" id="status-reason" label="Lý do thay đổi (bắt buộc khi hủy bỏ, mở lại hoặc yêu cầu sửa)" maxlength="2000" placeholder="Nhập lý do chi tiết nếu bạn hủy hoặc yêu cầu chỉnh sửa..." />
                </div>

                <div class="actions">
                    @foreach($workflowUi['actions'] as $action)
                        <div>
                            <button type="submit"
                                    name="status"
                                    value="{{ $action['status'] }}"
                                    data-reason="{{ $action['needs_reason']?'1':'0' }}"
                                    @disabled(!$action['enabled'])
                                    class="{{ $action['status']==='CANCELLED'?'danger':($action['status']==='DONE'?'':'secondary') }}"
                                    style="{{ $action['status']==='DONE' ? 'background: var(--success) !important;' : '' }}">
                                {{ $action['label'] }}
                            </button>
                            @if($action['reason'])
                                <p class="workflow-blocked">{{ $action['reason'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </form>
        </div>
    @endif
</section>

<!-- Blockers Section -->
<section class="card" style="border-left: 4px solid var(--danger);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <div>
            <h2 style="font-size: 1.15rem; margin: 0; color: var(--danger);">🚫 Vấn đề đang chặn (Blockers)</h2>
            <p class="muted" style="margin: 0.25rem 0 0 0; font-size: 0.85rem;">Ghi nhận các trở ngại khiến công việc không thể tiếp tục thực hiện.</p>
        </div>
    </div>

    @foreach($blockers as $blocker)
        <article class="card" data-blocker-id="{{ $blocker->id }}" style="background: {{ $blocker->status->value==='open' ? 'var(--danger-bg)' : 'var(--bg-subtle)' }} !important; border-color: {{ $blocker->status->value==='open' ? 'var(--danger-border)' : 'var(--border)' }}; margin: 0.75rem 0; padding: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.75rem;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                        <span class="badge" style="{{ $blocker->status->value==='open' ? 'background: #fff1f2; color: #be123c;' : 'background: #ecfdf5; color: #047857;' }}">
                            {{ \App\Support\UiLabels::text($blocker->status->value) }}
                        </span>
                        <small class="muted">
                            Báo bởi <strong>{{ $blocker->reporter?->name }}</strong> · {{ $blocker->created_at->setTimezone($workspace->timezone)->format('d/m/Y H:i') }}
                            @if($blocker->resolved_at) · Xử lý bởi <strong>{{ $blocker->resolver?->name }}</strong> @endif
                        </small>
                    </div>
                    <p style="margin: 0; font-weight: 600; color: var(--text);">{{ $blocker->reason }}</p>
                </div>
                @can('resolveBlocker',[$task,$blocker])
                    <form method="POST" action="{{ route($routePrefix.'.blockers.update',[...$routeParams,$task,$blocker->id]) }}" data-form="blocker-update" style="margin: 0;">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ $blocker->status->value==='open'?'resolved':'open' }}">
                        <button type="submit" class="secondary" style="font-size: 0.8rem; padding: 0.35rem 0.75rem; min-height: 32px;">
                            {{ $blocker->status->value==='open'?'Đánh dấu đã xử lý':'Mở lại blocker' }}
                        </button>
                    </form>
                @endcan
            </div>
        </article>
    @endforeach

    @can('block',$task)
        @if(!in_array($task->status->value,['DONE','CANCELLED'],true) && $task->task_type->value!=='GROUP')
            <form method="POST" action="{{ route($routePrefix.'.blockers.store',[...$routeParams,$task]) }}" data-form="blocker-create" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-light);">
                @csrf
                <x-textarea name="reason" id="blocker-reason" label="Báo cáo vấn đề đang chặn công việc này" required maxlength="4000" placeholder="Mô tả chi tiết nguyên nhân, sự cố hoặc phụ thuộc chưa giải quyết..." />
                <button type="submit" class="danger">Báo blocker</button>
            </form>
        @endif
    @endcan
</section>

<!-- Dependencies (FS) Section -->
<section class="card" style="border-left: 4px solid #8b5cf6;">
    <h2 style="font-size: 1.15rem; margin-bottom: 0.25rem;">🔗 Quan hệ phụ thuộc (Dependency FS)</h2>
    <p class="muted" style="margin-bottom: 1rem; font-size: 0.85rem;">Công việc này chỉ có thể bắt đầu sau khi các công việc tiên quyết (Predecessor) hoàn thành.</p>

    @forelse($dependencies as $edge)
        <article class="card" style="background: var(--bg-subtle) !important; margin: 0.65rem 0; padding: 0.85rem 1.15rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <div>
                <span class="muted" style="font-size: 0.8rem;">Chờ:</span>
                <a href="{{ route($routePrefix.'.show',[...$routeParams,$edge->predecessor]) }}" style="font-weight: 700; margin: 0 0.4rem;">
                    {{ $edge->predecessor->code }} — {{ $edge->predecessor->title }}
                </a>
                <span class="status-pill status-{{ strtolower(str_replace('_','-',$edge->predecessor->status->value)) }}" style="font-size: 0.7rem; padding: 0.15rem 0.5rem;">
                    {{ \App\Support\UiLabels::text($edge->predecessor->status) }}
                </span>
                <span class="muted" style="margin-left: 0.5rem; font-size: 0.8rem;">
                    · Chờ {{ $edge->lag_minutes }} phút sau hoàn tất
                </span>
            </div>
            @can('link',$task)
                <form method="POST" action="{{ route($routePrefix.'.dependencies.destroy',[...$routeParams,$task,$edge->id]) }}" data-form="dependency-delete" style="margin: 0;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="danger" style="font-size: 0.75rem; padding: 0.3rem 0.65rem; min-height: 28px;">Gỡ bỏ</button>
                </form>
            @endcan
        </article>
    @empty
        <p class="muted" style="font-style: italic;">Công việc này không phụ thuộc vào task nào khác.</p>
    @endforelse

    @can('link',$task)
        @if($task->status->value==='TODO' && $task->task_type->value!=='GROUP')
            <form method="POST" action="{{ route($routePrefix.'.dependencies.store',[...$routeParams,$task]) }}" data-form="dependency-create" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-light);">
                @csrf
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 1rem; align-items: end;">
                    <div>
                        <label for="predecessor_task_id">Chọn công việc phải hoàn tất trước</label>
                        <select name="predecessor_task_id" id="predecessor_task_id" required>
                            @foreach($candidates as $candidate)
                                <option value="{{ $candidate->id }}">{{ $candidate->code }} — {{ $candidate->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input name="lag_minutes" label="Thời gian chờ sau hoàn tất (phút)" type="number" value="0" min="0" max="525600" required />
                    </div>
                </div>
                <div style="margin-top: 1rem;">
                    <button type="submit">Thêm dependency</button>
                </div>
            </form>
        @endif
    @endcan
</section>

<!-- Comments and Attachments -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr)); gap: 1.25rem;">
    <!-- Comment Box -->
    <section class="card" style="margin: 0;">
        <h2 style="font-size: 1.15rem; margin-bottom: 0.5rem;">💬 Thêm bình luận</h2>
        @can('comment',$task)
            <form method="POST" action="{{ route($routePrefix.'.comments.store',[...$routeParams,$task]) }}" data-form="comment-create">
                @csrf
                <x-textarea name="content" label="Nội dung trao đổi" required maxlength="10000" placeholder="Viết trao đổi hoặc ghi chú về công việc này..." />
                <button type="submit">Gửi bình luận</button>
            </form>
        @else
            <p class="muted">Bạn không có quyền bình luận trên công việc này.</p>
        @endcan
    </section>

    <!-- Attachments Box -->
    <section class="card" style="margin: 0;">
        <h2 style="font-size: 1.15rem; margin-bottom: 0.5rem;">📎 Tệp đính kèm</h2>
        @can('attach',$task)
            <form method="POST" action="{{ route($routePrefix.'.attachments.store',[...$routeParams,$task]) }}" enctype="multipart/form-data" data-form="attachment-create">
                @csrf
                <div class="field">
                    <label for="task-file">Chọn tệp từ máy tính</label>
                    <input type="file" name="file" id="task-file" accept=".pdf,.png,.jpg,.jpeg,.txt,.csv,.docx,.xlsx" required>
                    <p class="muted" style="font-size: 0.8rem; margin-top: 0.35rem;">
                        Định dạng: PDF, PNG, JPG, TXT, CSV, DOCX, XLSX (tối đa {{ config('tasks.attachment_max_kb') }} KB).
                    </p>
                </div>
                <button type="submit">Tải tệp lên</button>
            </form>
        @else
            <p class="muted">Bạn không có quyền đính kèm tệp trên công việc này.</p>
        @endcan
    </section>
</div>

<!-- Activity & History Stream -->
<section class="card" data-page="task-activities" style="margin-top: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border);">
        <h2 style="font-size: 1.25rem; margin: 0;">📜 Nhật ký hoạt động & Lịch sử</h2>
        <form method="GET" action="{{ route($routePrefix.'.show',[...$routeParams,$task]) }}" style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <label for="activity_type" style="margin: 0; font-size: 0.85rem;">Lọc loại:</label>
            <select name="activity_type" id="activity_type" style="padding: 0.35rem 0.65rem; font-size: 0.85rem; min-height: 34px;">
                <option value="">Tất cả hoạt động</option>
                @foreach(['COMMENT'=>'Bình luận','HISTORY'=>'Lịch sử hệ thống','ATTACHMENT'=>'Tệp đính kèm'] as $value=>$label)
                    <option value="{{ $value }}" @selected(request('activity_type')===$value)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="secondary" style="min-height: 34px; padding: 0.35rem 0.75rem; font-size: 0.8rem;">Lọc</button>
        </form>
    </div>

    @foreach($activities as $activity)
        <article class="card" data-activity-id="{{ $activity->id }}" style="margin: 0.85rem 0; padding: 1.15rem; background: var(--bg-subtle) !important; border-left: 3px solid var(--border);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 1rem;">
                        {{ $activity->type->value==='COMMENT' ? '💬' : ($activity->type->value==='ATTACHMENT' ? '📎' : '⚡') }}
                    </span>
                    <strong>{{ $activity->actor?->name ?? 'Hệ thống' }}</strong>
                    <span class="badge" style="font-size: 0.7rem;">
                        {{ ['COMMENT'=>'Bình luận','HISTORY'=>'Lịch sử','ATTACHMENT'=>'Tệp đính kèm'][$activity->type->value] }}
                    </span>
                </div>
                <small class="muted" style="font-family: var(--font-mono); font-size: 0.75rem;">
                    {{ $activity->created_at->setTimezone($workspace->timezone)->format('d/m/Y H:i') }}
                </small>
            </div>

            @if($activity->type->value==='COMMENT')
                <div style="background: var(--surface); padding: 0.85rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light); margin: 0.5rem 0;">
                    <p style="white-space: pre-wrap; margin: 0; color: var(--text);">{{ $activity->content }}</p>
                </div>
                @can('comment',$task)
                    <div class="actions" style="margin-top: 0.5rem;">
                        @if($activity->actor_id===auth()->id())
                            <details style="font-size: 0.85rem; width: 100%;">
                                <summary style="cursor: pointer; color: var(--primary); font-weight: 600;">Sửa bình luận</summary>
                                <form method="POST" action="{{ route($routePrefix.'.comments.update',[...$routeParams,$task,$activity->id]) }}" data-form="comment-update" style="margin-top: 0.5rem;">
                                    @csrf
                                    @method('PATCH')
                                    <textarea id="comment-{{ $activity->id }}" name="content" required maxlength="10000" rows="3">{{ $activity->content }}</textarea>
                                    <button type="submit" style="margin-top: 0.4rem; font-size: 0.8rem; padding: 0.35rem 0.75rem; min-height: 32px;">Lưu sửa</button>
                                </form>
                            </details>
                        @endif
                        @if($activity->actor_id===auth()->id() || $canAssign)
                            <form method="POST" action="{{ route($routePrefix.'.comments.destroy',[...$routeParams,$task,$activity->id]) }}" data-form="comment-delete" style="margin: 0;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="danger" style="font-size: 0.75rem; padding: 0.25rem 0.65rem; min-height: 28px;">Xóa bình luận</button>
                            </form>
                        @endif
                    </div>
                @endcan
            @elseif($activity->type->value==='ATTACHMENT')
                <div style="display: flex; justify-content: space-between; align-items: center; background: var(--surface); padding: 0.75rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light); margin: 0.5rem 0;">
                    <span style="font-weight: 600;">{{ $activity->content }}</span>
                    @if(($activity->data['disk']??null)==='task_files')
                        <a href="{{ route($routePrefix.'.attachments.download',[...$routeParams,$task,$activity->id]) }}" class="button" style="min-height: 30px; font-size: 0.75rem; padding: 0.25rem 0.65rem;">
                            ⬇ Tải xuống
                        </a>
                    @endif
                </div>
                @can('attach',$task)
                    @if($activity->actor_id===auth()->id() || $canAssign)
                        <form method="POST" action="{{ route($routePrefix.'.attachments.destroy',[...$routeParams,$task,$activity->id]) }}" data-form="attachment-delete" style="margin-top: 0.5rem;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="danger" style="font-size: 0.75rem; padding: 0.25rem 0.65rem; min-height: 28px;">Gỡ tệp</button>
                        </form>
                    @endif
                @endcan
            @else
                <p style="margin: 0.25rem 0; font-weight: 600;">
                    {{ ['task.deadline_changed'=>'Thay đổi hạn có lý do','timer.corrected'=>'Điều chỉnh phiên giờ có audit','plan.applied'=>'Áp dụng kế hoạch','plan.calendar_replaced'=>'Thay lịch bằng phiên bản mới','timer.started'=>'Bắt đầu giờ làm','timer.stopped'=>'Kết thúc giờ làm','github.commit'=>'Commit GitHub liên quan','task.created'=>'Tạo công việc','task.updated'=>'Cập nhật công việc','task.status_changed'=>'Đổi trạng thái','task.group_synced'=>'Tổng hợp nhóm','task.archived'=>'Lưu trữ','task.restored'=>'Khôi phục','task.dependency_added'=>'Thêm dependency','task.dependency_removed'=>'Gỡ dependency','task.blocker_reported'=>'Báo blocker','task.blocker_updated'=>'Xử lý blocker','task.comment_added'=>'Thêm bình luận','task.comment_updated'=>'Sửa bình luận','task.comment_deleted'=>'Xóa bình luận','task.file_uploaded'=>'Thêm tệp','task.file_removed'=>'Gỡ tệp'][$activity->event_type] ?? 'Sự kiện hệ thống' }}
                </p>
                @if($activity->event_type==='github.commit')
                    <div style="background: var(--surface); padding: 0.6rem 0.85rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light); font-size: 0.85rem; margin: 0.4rem 0;">
                        <code style="font-family: var(--font-mono); color: var(--primary);">{{ $activity->data['commit']??'' }}</code>
                        <p style="white-space: pre-wrap; margin: 0.25rem 0 0 0;">{{ $activity->data['message']??'' }}</p>
                    </div>
                @endif
                @if($activity->plan_id && app(\App\Services\PlanService::class)->allowed(auth()->user(),$workspace,$project,'plan.view'))
                    <p style="margin: 0.35rem 0;"><a href="{{ route('workspaces.plans.show',[$workspace,$activity->plan_id]) }}">Xem phiên bản kế hoạch ›</a></p>
                @endif
                @if(isset($activity->data['from']))
                    <p style="margin: 0.25rem 0; font-size: 0.85rem;">
                        <span class="badge">{{ \App\Support\UiLabels::text($activity->data['from']) }}</span> →
                        <span class="badge" style="background: var(--primary-light); color: var(--primary);">{{ \App\Support\UiLabels::text($activity->data['to']) }}</span>
                    </p>
                @endif
                @if(!empty($activity->data['reason']))
                    <p style="margin: 0.25rem 0; font-size: 0.85rem; color: var(--text-muted);">Lý do: <em>{{ $activity->data['reason'] }}</em></p>
                @endif
                @if(isset($activity->data['after']))
                    <dl class="diff-fields" style="margin-top: 0.5rem; font-size: 0.8rem;">
                        @foreach($activity->data['after'] as $field=>$value)
                            <dt style="font-weight: 600;">{{ ['started_at'=>'Bắt đầu (UTC)','ended_at'=>'Kết thúc (UTC)','note'=>'Ghi chú','title'=>'Tiêu đề','description'=>'Mô tả','code'=>'Mã','task_type'=>'Loại','status'=>'Trạng thái','priority'=>'Ưu tiên','assignee_id'=>'Người thực hiện','reviewer_id'=>'Người duyệt','parent_task_id'=>'Nhóm cha','estimate_minutes'=>'Ước lượng (phút)','deadline_at'=>'Hạn','sort_order'=>'Thứ tự','group_label'=>'Nhãn nhóm','revision'=>'Bản dữ liệu'][$field] ?? $field }}:</dt>
                            <dd style="margin: 0;">{{ $activityHuman($field,$activity->data['before'][$field]??null) }} → <strong>{{ $activityHuman($field,$value) }}</strong></dd>
                        @endforeach
                    </dl>
                @endif
            @endif
        </article>
    @endforeach

    <div style="margin-top: 1.25rem;">
        {{ $activities->links() }}
    </div>
</section>
