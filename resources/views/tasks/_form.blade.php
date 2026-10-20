<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    @if($task)
        <input type="hidden" name="expected_revision" value="{{ old('expected_revision', $task->revision) }}">
        <div class="notice" style="background: rgba(99, 102, 241, 0.08); border-left: 3px solid var(--color-primary); padding: 0.75rem 1rem; border-radius: var(--radius-sm); font-size: 0.8125rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <span>Đang sửa phiên bản <strong>#{{ $task->revision }}</strong> của công việc <strong>{{ $task->code }}</strong>.</span>
            <a href="{{ request()->url() }}?fresh=1" style="color: var(--color-primary); font-weight: 500;">Tải lại dữ liệu gốc từ máy chủ</a>
        </div>
    @endif

    {{-- Nhóm 1: Loại công việc & Vị trí phân cấp --}}
    <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1.25rem;">
        <h3 style="font-size: 0.9375rem; font-weight: 700; margin: 0 0 1rem; color: var(--color-text); text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--color-primary);"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            Phân loại & Cấu trúc phân cấp
        </h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 1rem; margin-bottom: 1rem;">
            <div class="field" style="margin: 0;">
                <label for="task_type" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.375rem; color: var(--color-text);">Loại công việc</label>
                <select id="task_type" name="task_type" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text);">
                    @foreach(['GROUP' => 'Nhóm công việc (Group)', 'WORK' => 'Công việc thực thi (Work)', 'MILESTONE' => 'Cột mốc (Milestone)'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('task_type', $task?->task_type->value ?? 'WORK') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field" style="margin: 0;">
                <label for="parent_task_id" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.375rem; color: var(--color-text);">Thuộc nhóm cha</label>
                <select id="parent_task_id" name="parent_task_id" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text);">
                    <option value="">(Cấp cao nhất / Không có nhóm cha)</option>
                    @foreach($parents as $parent)
                        @if($parent->id !== $task?->id)
                            <option value="{{ $parent->id }}" @selected(old('parent_task_id', $task?->parent_task_id) === $parent->id)>
                                {{ $parent->code }} — {{ $parent->title }}
                            </option>
                        @endif
                    @endforeach
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
            <x-input name="group_label" label="Nhãn nhóm (tùy chọn)" :value="$task?->group_label ?? ''" maxlength="120" placeholder="Ví dụ: Backend, Frontend, UI Phase 1..." />
            <x-input name="sort_order" label="Thứ tự hiển thị" type="number" :value="$task?->sort_order ?? 0" min="0" max="1000000" />
        </div>

        <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 0.75rem; line-height: 1.4;">
            ℹ️ Cây phân rã tối đa <strong>{{ config('tasks.max_depth') }} cấp</strong>. Loại GROUP và MILESTONE không nhập ước lượng thời gian; GROUP không phân công trực tiếp người làm.
        </div>
    </div>

    {{-- Nhóm 2: Nội dung & Mô tả --}}
    <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1.25rem;">
        <h3 style="font-size: 0.9375rem; font-weight: 700; margin: 0 0 1rem; color: var(--color-text); text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--color-primary);"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Nội dung công việc
        </h3>

        <div style="display: grid; grid-template-columns: 1fr 3fr; gap: 1rem; margin-bottom: 1rem;">
            <x-input name="code" label="Mã định danh task" :value="$task?->code ?? ''" maxlength="40" placeholder="Để trống để tự tạo" />
            <x-input name="title" label="Tiêu đề công việc" :value="$task?->title ?? ''" required maxlength="200" placeholder="Nhập tóm tắt công việc cần thực hiện..." />
        </div>

        <x-textarea name="description" label="Mô tả yêu cầu chi tiết" :value="$task?->description ?? ''" maxlength="20000" placeholder="Mục tiêu, tiêu chí nghiệm thu (Acceptance Criteria), hướng dẫn kỹ thuật..." />
    </div>

    {{-- Nhóm 3: Ưu tiên & Thời gian --}}
    <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1.25rem;">
        <h3 style="font-size: 0.9375rem; font-weight: 700; margin: 0 0 1rem; color: var(--color-text); text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--color-primary);"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Ưu tiên & Kế hoạch thời gian
        </h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 1rem; margin-bottom: 1rem;">
            <div class="field" style="margin: 0;">
                <label for="priority" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.375rem; color: var(--color-text);">Độ ưu tiên</label>
                <select id="priority" name="priority" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text);">
                    @foreach(\App\Enums\TaskPriority::cases() as $priority)
                        <option value="{{ $priority->value }}" @selected(old('priority', $task?->priority->value ?? 'medium') === $priority->value)>
                            {{ \App\Support\UiLabels::text($priority) }}
                        </option>
                    @endforeach
                </select>
            </div>

            @if(!$task || $task->task_type->value === 'WORK')
                <x-input name="estimate_minutes" label="Thời lượng ước lượng (phút)" type="number" :value="$task?->estimate_minutes ?? ''" min="0" max="10000000" placeholder="Ví dụ: 120" />
            @endif

            <x-input name="deadline_at" :label="'Hạn chót — '.$workspace->timezone" type="datetime-local" :value="$task?->deadline_at?->setTimezone($workspace->timezone)->format('Y-m-d\TH:i') ?? ''" />
        </div>

        @if($task)
            <div style="margin-top: 0.75rem;">
                <x-textarea name="deadline_reason" label="Lý do đổi hạn (bắt buộc nếu thay đổi hạn chót)" maxlength="2000" placeholder="Nêu rõ nguyên nhân phát sinh thay đổi deadline để ghi nhật ký audit..." />
            </div>
        @endif
    </div>

    {{-- Nhóm 4: Phân công nhân sự --}}
    @if($canAssign)
    <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1.25rem;">
        <h3 style="font-size: 0.9375rem; font-weight: 700; margin: 0 0 1rem; color: var(--color-text); text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--color-primary);"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Phân công trách nhiệm
        </h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr)); gap: 1rem;">
            @foreach(['assignee_id' => 'Người thực hiện (Assignee)', 'reviewer_id' => 'Người duyệt kết quả (Reviewer)'] as $field => $label)
                <div class="field" style="margin: 0;">
                    <label for="{{ $field }}" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.375rem; color: var(--color-text);">{{ $label }}</label>
                    <select id="{{ $field }}" name="{{ $field }}" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text);">
                        <option value="">-- Chưa phân công --</option>
                        @php($selectPeople = $field === 'reviewer_id' ? $reviewers : $people)
                        @php($currentId = $task?->getAttribute($field))
                        @if($currentId && !$selectPeople->contains('user_id', $currentId))
                            <option value="{{ $currentId }}" @selected(old($field, $currentId) === $currentId)>
                                Giữ người đã phân công (hiện không còn đủ điều kiện)
                            </option>
                        @endif
                        @foreach($selectPeople as $person)
                            <option value="{{ $person->user_id }}" @selected(old($field, $currentId) === $person->user_id)>
                                {{ $person->user->name }} ({{ $person->user->email }})
                            </option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="actions" style="display: flex; gap: 0.75rem; align-items: center; padding-top: 1rem; border-top: 1px solid var(--color-border);">
        <button type="submit" class="button" style="padding: 0.625rem 1.5rem; font-weight: 600;">
            {{ $task ? 'Cập nhật công việc' : 'Tạo công việc' }}
        </button>
        <a class="button secondary" href="{{ route($routePrefix.'.index', $routeParams) }}">
            Hủy và quay lại danh sách
        </a>
    </div>
</div>
