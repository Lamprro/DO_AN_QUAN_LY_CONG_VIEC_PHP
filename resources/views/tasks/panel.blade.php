@php($taskParams = $task ? [...$routeParams, $task] : $routeParams)
<section data-panel-content data-panel-mode="{{ $panelMode }}" class="jira-slide-panel">
    <div class="panel-header-badge">
        <p class="eyebrow">{{ $workspace->name }} @if($project) / {{ $project->name }} @endif @if($task) • <strong>{{ $task->code }}</strong> @endif</p>
    </div>
    <h2 id="task-panel-title">
        {{ ['create' => 'Tạo công việc mới', 'edit' => 'Chỉnh sửa công việc', 'status' => 'Cập nhật trạng thái', 'view' => $task?->title][$panelMode] ?? 'Chi tiết công việc' }}
    </h2>

    @if(in_array($panelMode, ['create', 'edit']))
        @if(!empty($selectedParent))
            <div class="notice" style="background: rgba(99, 102, 241, 0.1); border-left: 4px solid var(--primary); padding: 10px 14px; margin-bottom: 14px; border-radius: 6px;">
                <p style="margin: 0; font-size: 0.85rem; color: var(--text);">
                    Đang tạo công việc con cho: <strong style="color: var(--primary);">{{ $selectedParent->code }} — {{ $selectedParent->title }}</strong>
                    <span class="badge" style="margin-left: 6px;">{{ $selectedParent->task_type->value === 'GROUP' ? 'Cấp 2 (Task)' : 'Cấp 3 (Sub-task)' }}</span>
                </p>
            </div>
        @endif
        <p class="muted">Lưu ngay tại đây. Danh sách và bảng sẽ cập nhật tức thì.</p>
        <form method="post" action="{{ route($routePrefix.($task ? '.update' : '.store'), $taskParams) }}" data-panel-form data-form="task-panel-edit">
            @csrf
            @if(!empty($selectedParent) && !$task)
                <input type="hidden" name="parent_task_id" value="{{ $selectedParent->id }}">
            @endif
            @if($task)
                @method('PATCH')
                <input type="hidden" name="expected_revision" value="{{ $task->revision }}">
                <input type="hidden" name="edit_surface" value="quick">
            @endif

            <div class="field">
                <label for="panel-title">Tiêu đề công việc <span class="required">*</span></label>
                <input id="panel-title" name="title" value="{{ $task?->title }}" required maxlength="200" autofocus placeholder="Nhập tóm tắt công việc cần hoàn thành…">
            </div>

            <div class="field">
                <label for="panel-description">Mô tả chi tiết</label>
                <textarea id="panel-description" name="description" rows="3" maxlength="20000" placeholder="Mục tiêu, tiêu chí nghiệm thu, kết quả mong đợi…">{{ $task?->description }}</textarea>
            </div>

            <div class="quick-edit-grid">
                <div class="field">
                    <label for="panel-priority">Mức ưu tiên</label>
                    <select id="panel-priority" name="priority">
                        @foreach(\App\Enums\TaskPriority::cases() as $p)
                            <option value="{{ $p->value }}" @selected(($task?->priority->value ?? 'medium') === $p->value)>
                                {{ \App\Support\UiLabels::text($p) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if(!$task || $task->task_type->value === 'WORK')
                    <div class="field" data-edit-field>
                        <label for="panel-estimate">Ước lượng thời gian (phút)</label>
                        <input id="panel-estimate" name="estimate_minutes" type="number" min="0" max="10000000" value="{{ $task?->estimate_minutes }}" placeholder="VD: 60">
                        <div class="estimate-presets field-presets">
                            @foreach([30 => '30 phút', 60 => '1 giờ', 120 => '2 giờ'] as $v => $label)
                                <button type="button" class="preset-chip" data-set="{{ $v }}">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="field" data-edit-field>
                <label for="panel-deadline">Hạn hoàn thành (Múi giờ: {{ $workspace->timezone }})</label>
                <input id="panel-deadline" name="deadline_at" type="datetime-local" value="{{ $task?->deadline_at?->setTimezone($workspace->timezone)->format('Y-m-d\TH:i') }}">
                @include('tasks._deadline-presets')
            </div>

            @if($task)
                <div class="field">
                    <label for="panel-deadline-reason">Lý do điều chỉnh hạn (nếu có)</label>
                    <textarea id="panel-deadline-reason" name="deadline_reason" maxlength="2000" rows="2" placeholder="Ghi chú nguyên nhân thay đổi hạn chót…"></textarea>
                </div>
            @endif

            @if($canAssign && (!$task || $task->task_type->value !== 'GROUP'))
                <div class="quick-edit-grid">
                    @foreach(['assignee_id' => 'Người thực hiện', 'reviewer_id' => 'Người duyệt'] as $field => $label)
                        @php($options = $field === 'reviewer_id' ? $reviewers : $people)
                        @php($current = $task?->getAttribute($field))
                        <div class="field">
                            <label for="panel-{{ $field }}">{{ $label }}</label>
                            <select id="panel-{{ $field }}" name="{{ $field }}">
                                <option value="">Chưa phân công</option>
                                @if($current && !$options->contains('user_id', $current))
                                    <option value="{{ $current }}" selected>Giữ người hiện tại (không còn trong danh sách)</option>
                                @endif
                                @foreach($options as $m)
                                    <option value="{{ $m->user_id }}" @selected($current === $m->user_id)>{{ $m->user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            @endif

            @if(!$task)
                <details class="panel-options" {{ !empty($selectedParent) ? 'open' : '' }}>
                    <summary>Tùy chọn nâng cao: Loại việc, nhóm cha và mã</summary>
                    <div class="quick-edit-grid">
                        <div class="field">
                            <label for="panel-type">Loại công việc</label>
                            <select id="panel-type" name="task_type">
                                <option value="WORK" @selected(empty($selectedParent) || $selectedParent->task_type->value !== 'GROUP')>Công việc chuẩn (Task)</option>
                                <option value="GROUP" @selected(empty($selectedParent) && old('task_type') === 'GROUP')>Nhóm công việc (Group/Epic)</option>
                                <option value="MILESTONE">Cột mốc (Milestone)</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="panel-parent">Nhóm công việc cha</label>
                            <select id="panel-parent" name="parent_task_id">
                                <option value="">Không có (Cấp cao nhất)</option>
                                @foreach($parents as $p)
                                    <option value="{{ $p->id }}" @selected(old('parent_task_id', $selectedParent?->id ?? request('parent_task_id')) === $p->id)>
                                        {{ $p->code }} — {{ $p->title }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="field">
                        <label for="panel-code">Mã công việc (để trống hệ thống sẽ tự sinh)</label>
                        <input id="panel-code" name="code" maxlength="40" placeholder="VD: TASK-01">
                    </div>
                </details>
            @endif

            <div class="panel-form-actions">
                <button type="button" class="secondary" data-panel-close>Đóng</button>
                <button type="submit" class="button primary">{{ $task ? 'Lưu thay đổi' : 'Tạo công việc' }}</button>
            </div>
        </form>

    @elseif($panelMode === 'status')
        <div class="status-panel-info">
            <p><strong>{{ $task->title }}</strong></p>
            <p>Trạng thái hiện tại: <x-status :value="$task->status" /></p>
        </div>

        <form method="post" action="{{ route($routePrefix.'.status.update', $taskParams) }}" data-panel-form data-form="task-status">
            @csrf
            @method('PATCH')
            <input type="hidden" name="expected_status" value="{{ $task->status->value }}">

            @foreach($workflowUi['notices'] as $notice)
                <p class="notice warning">{{ $notice }}</p>
            @endforeach

            <div class="field">
                <label for="panel-reason">Lý do thay đổi (bắt buộc khi hủy, mở lại hoặc yêu cầu sửa)</label>
                <textarea id="panel-reason" name="reason" rows="3" maxlength="2000" placeholder="Ghi chú lý do chuyển trạng thái…"></textarea>
            </div>

            <div class="panel-status-actions">
                @foreach($workflowUi['actions'] as $action)
                    <div>
                        <button type="submit" name="status" value="{{ $action['status'] }}" data-reason="{{ $action['needs_reason'] ? '1' : '0' }}" @disabled(!$action['enabled']) class="button {{ $action['status'] === 'DONE' ? 'primary' : 'secondary' }}">
                            {{ $action['label'] }}
                        </button>
                        @if($action['reason'])
                            <p class="muted text-sm">{{ $action['reason'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </form>

    @else
        @if($task->parent)
            <div style="font-size: 0.85rem; padding: 8px 12px; background: rgba(99, 102, 241, 0.08); border-radius: 6px; border: 1px solid rgba(99, 102, 241, 0.2); margin-bottom: 14px;">
                <span class="muted">Thuộc việc cha:</span>
                <a href="{{ route($routePrefix.'.show', [...$routeParams, $task->parent]) }}" data-task-panel style="font-weight: 600; color: var(--primary); margin-left: 4px; text-decoration: none;">
                    {{ $task->parent->code }} — {{ $task->parent->title }}
                </a>
            </div>
        @endif

        <div style="margin-bottom: 12px;">
            @include('tasks._alerts', ['task' => $task])
        </div>

        <div class="panel-meta">
            <x-status :value="$task->status"/>
            <x-status :value="$task->priority"/>
        </div>

        <div class="panel-description-box">
            <h4>Mô tả</h4>
            <p class="panel-description">{{ $task->description ?: 'Chưa có mô tả chi tiết.' }}</p>
        </div>

        <dl class="panel-facts">
            <div>
                <dt>Người thực hiện</dt>
                <dd>{{ $task->assignee?->name ?? 'Chưa phân công' }}</dd>
            </div>
            <div>
                <dt>Người kiểm duyệt</dt>
                <dd>{{ $task->reviewer?->name ?? 'Chưa phân công' }}</dd>
            </div>
            <div>
                <dt>Hạn hoàn thành</dt>
                <dd>{{ $task->deadline_at?->setTimezone($workspace->timezone)->format('d/m/Y H:i') ?? 'Chưa đặt hạn' }}</dd>
            </div>
        </dl>

        <div class="panel-subtasks-section" style="margin-top: 18px; border-top: 1px solid var(--border); padding-top: 14px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="margin: 0; font-size: 0.95rem; font-weight: 700;">Công việc con ({{ count($subtasks ?? []) }})</h3>
                @can('addChild', $task)
                    <a class="button secondary compact" data-task-panel href="{{ route($routePrefix.'.create', [...$routeParams, 'parent_task_id' => $task->id]) }}" style="font-size: 0.75rem; padding: 4px 8px; min-height: 28px;">
                        + Thêm việc con
                    </a>
                @endcan
            </div>
            @if(count($subtasks ?? []) > 0)
                <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 6px;">
                    @foreach($subtasks as $child)
                        <li style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: var(--bg); border-radius: 6px; border: 1px solid var(--border-light); font-size: 0.85rem;">
                            <a href="{{ route($routePrefix.'.show', [...$routeParams, $child]) }}" data-task-panel style="font-weight: 600; text-decoration: none; color: var(--text);">
                                <span style="font-family: var(--font-mono); color: var(--primary);">{{ $child->code }}</span> {{ $child->title }}
                            </a>
                            <x-status :value="$child->status" />
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="muted" style="font-size: 0.85rem; margin: 0;">Chưa có công việc con.</p>
            @endif
        </div>

        <div class="actions panel-actions" style="margin-top: 16px;">
            @can('update', $task)
                <a class="button primary" data-task-panel href="{{ route($routePrefix.'.edit', $taskParams) }}">Chỉnh sửa</a>
            @endcan
            @if(count($workflowUi['actions']))
                <a class="button secondary" data-task-panel href="{{ route($routePrefix.'.status.edit', $taskParams) }}">Đổi trạng thái</a>
            @endif
        </div>

        <div class="panel-attachments-section" style="margin-top: 18px; border-top: 1px solid var(--border); padding-top: 14px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="margin: 0; font-size: 0.95rem; font-weight: 700;">Tệp & hình ảnh ({{ count($taskAttachments ?? []) }})</h3>
                <a href="{{ route($routePrefix.'.show', $taskParams) }}#task-discussion" style="font-size: 0.75rem; color: var(--primary); text-decoration: none;">+ Tải thêm tệp</a>
            </div>
            @if(count($taskAttachments ?? []) > 0)
                <div style="display: flex; flex-direction: column; gap: 6px;">
                    @foreach($taskAttachments as $attachment)
                        @php
                            $attData = $attachment->data ?? [];
                            $isImg = str_starts_with($attData['mime'] ?? '', 'image/');
                            $downloadUrl = route($routePrefix.'.attachments.download', [...$routeParams, $task, $attachment->id]);
                            $fileSizeKb = isset($attData['size']) ? round($attData['size'] / 1024, 1) : null;
                        @endphp
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 10px; background: var(--bg); border-radius: 6px; border: 1px solid var(--border-light); font-size: 0.85rem;">
                            <div style="display: flex; align-items: center; gap: 6px; min-width: 0;">
                                <span>{{ $isImg ? '🖼️' : '📄' }}</span>
                                <span style="font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 170px;" title="{{ $attachment->content }}">{{ $attachment->content }}</span>
                                @if($fileSizeKb)<small class="muted">({{ $fileSizeKb }}KB)</small>@endif
                            </div>
                            <a href="{{ $downloadUrl }}" class="button secondary compact" style="min-height: 24px; font-size: 11px; padding: 2px 8px;">⬇ Tải</a>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="muted" style="font-size: 0.85rem; margin: 0;">Chưa có tệp đính kèm.</p>
            @endif
        </div>

        <div class="panel-comments-section">
            <h3>Trao đổi gần đây</h3>
            @forelse($recentComments as $comment)
                <article class="panel-comment">
                    <header>
                        <strong>{{ $comment->actor?->name ?? 'Thành viên' }}</strong>
                        <time class="muted text-xs">{{ $comment->created_at?->diffForHumans() }}</time>
                    </header>
                    <p>{{ $comment->content }}</p>
                </article>
            @empty
                <p class="muted">Chưa có bình luận nào.</p>
            @endforelse
        </div>
    @endif

    <p class="panel-full-link">
        <a href="{{ route($routePrefix.($task ? '.show' : '.create'), $taskParams) }}">Mở trang đầy đủ ↗</a>
    </p>
</section>
