<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <div style="background: var(--color-surface); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
        <x-input name="name" label="Tên vai trò" :value="isset($role) ? $role->name : ''" required maxlength="100" placeholder="Ví dụ: Tech Lead, Project Manager, Reviewer..." />
    </div>

    @php($selectedPermissions = old('permissions', isset($role) ? $role->permissions : []))
    @php($selectedPermissions = is_array($selectedPermissions) ? $selectedPermissions : [])

    <div class="notice" style="background: rgba(99, 102, 241, 0.08); border-left: 4px solid var(--color-primary); padding: 1rem 1.25rem; border-radius: var(--radius-md); font-size: 0.875rem; color: var(--color-text);">
        <div style="font-weight: 600; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.5rem; color: var(--color-primary);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            Nguyên tắc cấp quyền và phân cấp
        </div>
        <p style="margin: 0; line-height: 1.5; color: var(--color-text-muted);">
            Quyền xem workspace là điều kiện tiên quyết để truy cập. Bạn chỉ có thể phân bổ các quyền nằm trong phạm vi quyền hạn cá nhân của bạn. Quyền hạn đối với project còn phụ thuộc vào trạng thái thành viên cụ thể trong từng project.
        </p>
    </div>

    <div style="display: flex; flex-direction: column; gap: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <h3 style="font-size: 1.125rem; font-weight: 700; margin: 0; color: var(--color-text);">Danh mục quyền hạn chi tiết</h3>
            <div style="max-width: 320px; width: 100%;">
                <input type="search" id="permission-search" placeholder="🔍 Lọc quyền theo từ khóa..." style="width: 100%; padding: 0.45rem 0.75rem; font-size: 0.8125rem; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--surface);">
            </div>
        </div>

        @foreach($permissionGroups as $group => $permissions)
        <fieldset class="card" style="padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--color-border); margin: 0; background: var(--color-surface);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem;">
                <legend style="padding: 0; font-weight: 700; font-size: 0.9375rem; color: var(--color-primary); text-transform: uppercase; letter-spacing: 0.05em; margin: 0;">
                    {{ $group }}
                </legend>
                <div class="permission-group-actions" style="display: flex; gap: 0.35rem; align-items: center;">
                    <button type="button" class="preset-chip btn-select-all" style="font-size: 0.75rem;">✓ Chọn tất cả</button>
                    <button type="button" class="preset-chip btn-deselect-all" style="font-size: 0.75rem;">✕ Bỏ chọn</button>
                </div>
            </div>
            <div class="permissions" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 260px), 1fr)); gap: 0.75rem;">
                @foreach($permissions as $permission)
                <label for="permission-{{ str_replace('.', '-', $permission['value']) }}" style="display: flex; align-items: flex-start; gap: 0.625rem; padding: 0.625rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface-hover); cursor: pointer; transition: all var(--transition-fast);">
                    <input id="permission-{{ str_replace('.', '-', $permission['value']) }}" type="checkbox" name="permissions[]" value="{{ $permission['value'] }}" @checked(in_array($permission['value'], $selectedPermissions, true)) style="margin-top: 0.1875rem;">
                    <div>
                        <div style="font-size: 0.875rem; font-weight: 600; color: var(--color-text);">
                            {{ $permission['label'] }}
                        </div>
                        <div style="font-size: 0.75rem; color: var(--color-text-muted); font-family: var(--font-mono); margin-top: 0.125rem;">
                            {{ $permission['value'] }}
                        </div>
                    </div>
                </label>
                @endforeach
            </div>
        </fieldset>
        @endforeach
    </div>

    {{-- Sticky Floating Action Bar --}}
    <div class="sticky-form-bar">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <span style="font-size: 0.875rem; font-weight: 600; color: var(--text);">
                {{ isset($role) ? 'Sửa vai trò: '.$role->name : 'Tạo vai trò mới' }}
            </span>
            <span class="muted" style="font-size: 0.75rem;">· Phím tắt:</span>
            <kbd class="kbd-shortcut">Ctrl + ↵</kbd>
        </div>
        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <a class="button secondary" href="{{ route('workspaces.roles.index', $workspace) }}" style="padding: 0.55rem 1rem;">
                Hủy bỏ
            </a>
            <button type="submit" class="button" style="padding: 0.55rem 1.5rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                <span>Lưu cấu hình vai trò</span>
                <span class="kbd-shortcut" style="background: rgba(255,255,255,0.25); color: #fff; border-color: rgba(255,255,255,0.35);">Ctrl+↵</span>
            </button>
        </div>
    </div>
</div>
