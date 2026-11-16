{{-- Shown only while rows are ticked (app-ui.js). Without JavaScript it stays visible. --}}
<form id="bulk-tasks" method="post" action="{{ route($routePrefix.'.bulk',$routeParams) }}" class="bulk-bar" data-form="task-bulk" data-bulk-bar @if($errors->has('items') || $errors->has('items.*') || $errors->has('action')) data-has-errors="1" @endif data-confirm="Lưu cả nhóm đã chọn? Một lỗi sẽ hủy toàn bộ thay đổi nhóm này.">
    @csrf
    <strong id="bulk-count" role="status" tabindex="-1">Đã chọn 0 việc</strong>
    <label class="sr-only" for="bulk-action">Thao tác</label>
    <select id="bulk-action" name="action">
        <option value="priority">Đổi ưu tiên</option>
        @if($canAssign)<option value="assign">Giao người làm</option>@endif
        <option value="archive">Lưu trữ</option>
    </select>
    <div class="bulk-value">
        <label class="sr-only" for="bulk-priority">Ưu tiên mới</label>
        <select id="bulk-priority" name="priority">
            @foreach(\App\Enums\TaskPriority::cases() as $priority)<option value="{{ $priority->value }}">{{ \App\Support\UiLabels::text($priority) }}</option>@endforeach
        </select>
    </div>
    <div class="bulk-value">
        <label class="sr-only" for="bulk-assignee">Người làm mới</label>
        <select id="bulk-assignee" name="assignee_id">
            <option value="">— Bỏ phân công —</option>
            @foreach($people as $person)<option value="{{ $person->user_id }}">{{ $person->user->name }}</option>@endforeach
        </select>
    </div>
    <button type="submit" class="compact">Áp dụng</button>
    <button type="button" class="secondary compact" data-bulk-clear>Bỏ chọn</button>
    <small class="muted">Tối đa 50 việc · một việc lỗi sẽ hủy cả nhóm</small>
</form>
