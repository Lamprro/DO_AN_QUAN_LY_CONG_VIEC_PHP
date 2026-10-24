@php($selectPeople = $people ?? collect())
@php($selectReviewers = $reviewers ?? $selectPeople)

<!-- Universal Quick Edit Modal -->
<div id="quick-edit-modal-backdrop" class="quick-edit-backdrop" role="dialog" aria-modal="true" aria-labelledby="quick-edit-modal-title" style="display: none;">
    <div class="quick-edit-dialog card" style="max-width: 640px; margin: auto; padding: 1.5rem; position: relative;">
        <div class="quick-edit-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border);">
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <span id="qm-task-code" class="badge" style="font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700; color: var(--primary); background: var(--primary-light);">CODE</span>
                <h2 id="quick-edit-modal-title" style="margin: 0; font-size: 1.2rem; font-weight: 700;">Sửa nhanh công việc</h2>
            </div>
            <button type="button" class="quick-edit-close" id="qm-close-btn" aria-label="Đóng cửa sổ">✕</button>
        </div>

        <form id="universal-quick-edit-form" method="POST" action="" data-dirty-form>
            @csrf
            @method('PATCH')
            <input type="hidden" name="edit_surface" value="quick">
            <input type="hidden" name="expected_revision" id="qm-expected-revision" value="1">

            <div id="qm-error-notice" class="notice error" style="display: none; margin-bottom: 1rem;">
                <span id="qm-error-message">Có lỗi xảy ra khi lưu.</span>
            </div>

            <!-- Title -->
            <div class="field" style="margin-bottom: 1rem;">
                <label for="qm-title" style="font-weight: 600;">Tiêu đề công việc <span style="color: var(--danger);">*</span></label>
                <input type="text" id="qm-title" name="title" required maxlength="200" placeholder="Nhập tiêu đề công việc..." style="width: 100%;">
            </div>

            <!-- Priority & Status Info -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="field">
                    <label for="qm-priority" style="font-weight: 600;">Mức ưu tiên</label>
                    <select id="qm-priority" name="priority" style="width: 100%;">
                        @foreach(\App\Enums\TaskPriority::cases() as $priority)
                            <option value="{{ $priority->value }}">{{ \App\Support\UiLabels::text($priority) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label style="font-weight: 600;">Trạng thái hiện tại</label>
                    <div style="padding-top: 0.35rem;">
                        <span id="qm-status-badge" class="status-pill status-todo">Chưa bắt đầu</span>
                    </div>
                </div>
            </div>

            <!-- Assignee & Reviewer -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="field">
                    <label for="qm-assignee" style="font-weight: 600;">Người thực hiện</label>
                    <select id="qm-assignee" name="assignee_id" style="width: 100%;">
                        <option value="">Chưa phân công</option>
                        @foreach($selectPeople as $person)
                            <option value="{{ $person->user_id }}">{{ $person->user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="qm-reviewer" style="font-weight: 600;">Người duyệt</label>
                    <select id="qm-reviewer" name="reviewer_id" style="width: 100%;">
                        <option value="">Chưa phân công</option>
                        @foreach($selectReviewers as $person)
                            <option value="{{ $person->user_id }}">{{ $person->user->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Estimate & Deadline -->
            <div style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="field" data-edit-field>
                    <label for="qm-estimate" style="font-weight: 600;">Ước lượng (phút)</label>
                    <input type="number" id="qm-estimate" name="estimate_minutes" min="0" max="10000000" placeholder="VD: 60" style="width: 100%;">
                    <div class="field-presets estimate-presets" style="margin-top: 0.35rem; display: flex; gap: 0.3rem; flex-wrap: wrap;">
                        <button type="button" class="preset-chip" data-set="30" style="font-size: 0.72rem; padding: 0.15rem 0.35rem;">30p</button>
                        <button type="button" class="preset-chip" data-set="60" style="font-size: 0.72rem; padding: 0.15rem 0.35rem;">1h</button>
                        <button type="button" class="preset-chip" data-set="120" style="font-size: 0.72rem; padding: 0.15rem 0.35rem;">2h</button>
                        <button type="button" class="preset-chip" data-set="240" style="font-size: 0.72rem; padding: 0.15rem 0.35rem;">4h</button>
                    </div>
                </div>
                <div class="field" data-edit-field>
                    <label for="qm-deadline" style="font-weight: 600;">Hạn chót</label>
                    <input type="datetime-local" id="qm-deadline" name="deadline_at" style="width: 100%;">
                    @include('tasks._deadline-presets')
                </div>
            </div>

            <!-- Deadline reason (shown if deadline is edited) -->
            <div class="field" id="qm-reason-group" style="margin-bottom: 1.25rem;">
                <label for="qm-deadline-reason" style="font-weight: 600;">Lý do thay đổi hạn chót</label>
                <textarea id="qm-deadline-reason" name="deadline_reason" maxlength="2000" placeholder="Nhập lý do đổi hạn (bắt buộc khi đổi hạn cũ sang hạn mới)..." rows="2" style="width: 100%;"></textarea>
            </div>

            <!-- Actions footer -->
            <div class="actions" style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                <button type="button" class="button secondary" id="qm-cancel-btn">Hủy bỏ</button>
                <button type="submit" class="button primary" id="qm-submit-btn">
                    <span>Lưu nhanh</span>
                    <span class="kbd-shortcut" style="margin-left: 0.4rem; font-size: 0.7rem; opacity: 0.85;">Ctrl+↵</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Quick Create Task Modal -->
<div id="quick-create-modal-backdrop" class="quick-edit-backdrop" role="dialog" aria-modal="true" aria-labelledby="quick-create-modal-title" style="display: none;">
    <div class="quick-edit-dialog card" style="max-width: 600px; margin: auto; padding: 1.5rem; position: relative;">
        <div class="quick-edit-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border);">
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <span style="font-size: 1.25rem;">✨</span>
                <h2 id="quick-create-modal-title" style="margin: 0; font-size: 1.2rem; font-weight: 700;">Tạo nhanh công việc mới</h2>
            </div>
            <button type="button" class="quick-edit-close" id="qc-close-btn" aria-label="Đóng cửa sổ">✕</button>
        </div>

        <form id="quick-create-form" method="POST" action="{{ route($routePrefix.'.store', $routeParams) }}" data-dirty-form>
            @csrf
            <div id="qc-error-notice" class="notice error" style="display: none; margin-bottom: 1rem;">
                <span id="qc-error-message">Có lỗi xảy ra.</span>
            </div>

            <div class="field" style="margin-bottom: 1rem;">
                <label for="qc-title" style="font-weight: 600;">Tiêu đề công việc <span style="color: var(--danger);">*</span></label>
                <input type="text" id="qc-title" name="title" required maxlength="200" placeholder="Nhập tiêu đề công việc cần làm..." style="width: 100%;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="field">
                    <label for="qc-priority" style="font-weight: 600;">Mức ưu tiên</label>
                    <select id="qc-priority" name="priority" style="width: 100%;">
                        @foreach(\App\Enums\TaskPriority::cases() as $priority)
                            <option value="{{ $priority->value }}" @selected($priority->value === 'medium')>{{ \App\Support\UiLabels::text($priority) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="qc-assignee" style="font-weight: 600;">Người thực hiện</label>
                    <select id="qc-assignee" name="assignee_id" style="width: 100%;">
                        <option value="">Chưa phân công</option>
                        @foreach($selectPeople as $person)
                            <option value="{{ $person->user_id }}" @selected($person->user_id === auth()->id())>{{ $person->user->name }} {{ $person->user_id === auth()->id() ? '(Tôi)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="field" data-edit-field>
                    <label for="qc-estimate" style="font-weight: 600;">Ước lượng (phút)</label>
                    <input type="number" id="qc-estimate" name="estimate_minutes" min="0" placeholder="VD: 60" style="width: 100%;">
                </div>
                <div class="field" data-edit-field>
                    <label for="qc-deadline" style="font-weight: 600;">Hạn chót</label>
                    <input type="datetime-local" id="qc-deadline" name="deadline_at" style="width: 100%;">
                </div>
            </div>

            <div class="actions" style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                <button type="button" class="button secondary" id="qc-cancel-btn">Hủy bỏ</button>
                <button type="submit" class="button primary" id="qc-submit-btn">
                    <span>+ Tạo công việc</span>
                    <span class="kbd-shortcut" style="margin-left: 0.4rem; font-size: 0.7rem; opacity: 0.85;">Ctrl+↵</span>
                </button>
            </div>
        </form>
    </div>
</div>
