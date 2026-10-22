@if($project)
    <input type="hidden" name="expected_revision" value="{{ old('expected_revision',$project->revision) }}">
    <div class="notice" style="background: rgba(99, 102, 241, 0.08); border-left: 3px solid var(--color-primary); padding: 0.75rem 1rem; border-radius: var(--radius-sm); font-size: 0.8125rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.25rem;">
        <span>Đang sửa phiên bản <strong>#{{ $project->revision }}</strong> của dự án <strong>{{ $project->name }}</strong>.</span>
        <a href="{{ request()->url() }}?fresh=1" style="color: var(--color-primary); font-weight: 500;">Tải lại dữ liệu gốc</a>
    </div>
@endif

<div style="display: flex; flex-direction: column; gap: 1.25rem;">
    <div style="background: var(--surface); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border);">
        <h3 style="font-size: 0.9375rem; font-weight: 700; margin: 0 0 1rem; color: var(--text); text-transform: uppercase; letter-spacing: 0.05em;">
            Thông tin định danh dự án
        </h3>
        <x-input name="name" label="Tên dự án" :value="$project?->name ?? ''" required maxlength="180" placeholder="Nhập tên dự án..." />
        <div style="margin-top: 1rem;">
            <x-textarea name="description" label="Mô tả mục tiêu dự án" :value="$project?->description ?? ''" maxlength="20000" placeholder="Mục tiêu, phạm vi và kế hoạch triển khai dự án..." />
        </div>
    </div>

    <div style="background: var(--surface); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border);">
        <h3 style="font-size: 0.9375rem; font-weight: 700; margin: 0 0 1rem; color: var(--text); text-transform: uppercase; letter-spacing: 0.05em;">
            Cấu trúc phân cấp & Trạng thái
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 1rem;">
            <div class="field" style="margin: 0;">
                <label for="parent_project_id" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.35rem;">Dự án cha (tùy chọn)</label>
                <select id="parent_project_id" name="parent_project_id" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--surface);">
                    <option value="">(Cấp cao nhất / Không có dự án cha)</option>
                    @foreach($parents as $parent)
                        <option value="{{ $parent->id }}" @selected(old('parent_project_id',$project?->parent_project_id)===$parent->id)>
                            {{ $parent->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="margin: 0;">
                <label for="status" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.35rem;">Trạng thái hoạt động</label>
                <select name="status" id="status" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--surface);">
                    @foreach(\App\Enums\ProjectStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(old('status',$project?->status->value ?? 'draft')===$status->value)>
                            {{ \App\Support\UiLabels::text($status) }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div style="background: var(--surface); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border);">
        <h3 style="font-size: 0.9375rem; font-weight: 700; margin: 0 0 1rem; color: var(--text); text-transform: uppercase; letter-spacing: 0.05em;">
            Kế hoạch thời gian
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 1rem;">
            <x-input name="start_date" label="Ngày bắt đầu dự án" type="date" :value="$project?->start_date?->format('Y-m-d') ?? ''" />
            <x-input name="deadline" label="Hạn hoàn thành mục tiêu" type="date" :value="$project?->deadline?->format('Y-m-d') ?? ''" />
        </div>
    </div>

    {{-- Sticky Floating Action Bar --}}
    <div class="sticky-form-bar">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <span style="font-size: 0.875rem; font-weight: 600; color: var(--text);">
                {{ $project ? 'Đang chỉnh sửa: '.$project->name : 'Đang tạo dự án mới' }}
            </span>
            <span class="muted" style="font-size: 0.75rem;">· Phím tắt:</span>
            <kbd class="kbd-shortcut">Ctrl + ↵</kbd>
        </div>
        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <a class="button secondary" href="{{ route('workspaces.projects.index',$workspace) }}" style="padding: 0.55rem 1rem;">
                Hủy bỏ
            </a>
            <button type="submit" class="button" style="padding: 0.55rem 1.5rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                <span>{{ $project ? 'Lưu thay đổi' : 'Tạo dự án' }}</span>
                <span class="kbd-shortcut" style="background: rgba(255,255,255,0.25); color: #fff; border-color: rgba(255,255,255,0.35);">Ctrl+↵</span>
            </button>
        </div>
    </div>
</div>
