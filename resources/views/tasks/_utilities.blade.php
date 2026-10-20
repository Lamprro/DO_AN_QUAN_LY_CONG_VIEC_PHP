@php($utilityPrefix=$project?'workspaces.projects':'workspaces')

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <!-- Save Filter & Saved Filters Card -->
    <div class="card" style="margin: 0; padding: 1.25rem;">
        <h3 style="font-size: 1rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.4rem;">
            <span>⭐</span> Bộ lọc cá nhân đã lưu
        </h3>

        <form method="post" action="{{ route($utilityPrefix.'.task-filters.store',$routeParams) }}" data-form="filter-save" style="margin-bottom: 0.75rem;">
            @csrf
            @foreach($filters as $key=>$value)
                <input type="hidden" name="query[{{ $key }}]" value="{{ $value }}">
            @endforeach
            @if(!$filters)
                <input type="hidden" name="query[preset]" value="all">
            @endif

            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <label class="sr-only" for="filter-name">Tên bộ lọc cá nhân</label><input name="name" id="filter-name" maxlength="80" required placeholder="Tên bộ lọc hiện tại (tối đa 20)..." style="flex: 1;">
                <button type="submit" class="secondary" style="white-space: nowrap; font-size: 0.8rem; padding: 0.4rem 0.8rem; min-height: 36px;">
                    Lưu bộ lọc
                </button>
            </div>
        </form>

        @if(count($savedFilters) > 0)
            <div style="display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.5rem;">
                @foreach($savedFilters as $saved)
                    <div style="display: inline-flex; align-items: center; gap: 0.35rem; background: var(--bg-subtle); padding: 0.25rem 0.6rem; border-radius: var(--radius-sm); border: 1px solid var(--border); font-size: 0.8rem;">
                        <a href="{{ route($utilityPrefix.'.task-filters.open',[...$routeParams,$saved]) }}" style="font-weight: 600;">
                            {{ $saved->name }}
                        </a>
                        <form method="post" action="{{ route($utilityPrefix.'.task-filters.destroy',[...$routeParams,$saved]) }}" data-form="filter-delete" style="display: inline; margin: 0;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="background: none !important; border: none !important; padding: 0 !important; min-height: auto !important; color: var(--danger) !important; cursor: pointer; font-size: 0.85rem; box-shadow: none !important;" title="Xóa bộ lọc">✕</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @else
            <p class="muted" style="margin: 0; font-size: 0.8rem;">Chưa có bộ lọc nào được lưu riêng cho tài khoản của bạn.</p>
        @endif
    </div>

    <!-- Bulk Actions Card -->
    <details class="card" style="margin: 0; padding: 1.25rem; border-left: 4px solid var(--primary);">
        <summary style="font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: space-between;">
            <span>⚡ Thao tác hàng loạt (Bulk Actions)</span>
            <small class="muted">Tối đa 50 việc</small>
        </summary>
        <p class="muted" style="font-size: 0.8rem; margin: 0.75rem 0;">
            Áp dụng trên các dòng được tích chọn ở bảng bên dưới. Nếu có một việc lỗi hoặc dữ liệu cũ, hệ thống sẽ tự động hủy toàn bộ thay đổi để bảo toàn dữ liệu.
        </p>
        <div class="selection-toolbar"><label><input type="checkbox" id="bulk-select-page"> Chọn các dòng có thể sửa trên trang</label><p id="bulk-count" role="status" tabindex="-1">Chọn công việc ở bảng bên dưới.</p></div>
        <form id="bulk-tasks" method="post" action="{{ route($routePrefix.'.bulk',$routeParams) }}" data-form="task-bulk" data-confirm="Lưu cả nhóm đã chọn? Một lỗi sẽ hủy toàn bộ thay đổi nhóm này.">
            @csrf
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 140px), 1fr)); gap: 0.75rem; align-items: end;">
                <div>
                    <label for="bulk-action">Thao tác</label>
                    <select id="bulk-action" name="action">
                        <option value="priority">Đổi mức ưu tiên</option>
                        @if($canAssign)
                            <option value="assign">Giao/đổi người làm</option>
                        @endif
                        <option value="archive">Lưu trữ</option>
                    </select>
                </div>
                <div>
                    <label for="bulk-priority">Ưu tiên mới</label>
                    <select id="bulk-priority" name="priority">
                        @foreach(\App\Enums\TaskPriority::cases() as $priority)
                            <option value="{{ $priority->value }}">{{ \App\Support\UiLabels::text($priority) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="bulk-assignee">Người làm mới</label>
                    <select id="bulk-assignee" name="assignee_id">
                        <option value="">-- Bỏ phân công --</option>
                        @foreach($people as $person)
                            <option value="{{ $person->user_id }}">{{ $person->user->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="margin-top: 1rem;">
                <button type="submit" style="width: 100%;">Thực hiện trên các dòng đã chọn</button>
            </div>
        </form>
    </details>
</div>
