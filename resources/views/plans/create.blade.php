<x-layout title="Soạn kế hoạch">
<div style="margin-bottom: 1rem;">
    <a href="{{ route('workspaces.plans.index',[$workspace,'project_id'=>$project?->id]) }}" style="font-size: 0.875rem; font-weight: 600;">
        ‹ Danh sách kế hoạch
    </a>
</div>

<section class="card" data-page="plan-create" style="border-top: 4px solid var(--primary); padding: 1.5rem;">
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ $project?->name ?? $workspace->name }} · {{ $workspace->timezone }}</p>
            <h1 style="margin-bottom: 0.35rem;">{{ $base ? 'Tạo phiên bản kế tiếp' : 'Tạo kế hoạch mới' }}</h1>
            <p class="muted" style="margin: 0;">
                Soạn thảo cấu trúc công việc, quan hệ phụ thuộc (FS) và khung giờ dự kiến. Lưu nháp sẽ chưa thay đổi công việc hoặc lịch thật.
            </p>
        </div>
    </div>

    <!-- Search Source Tasks -->
    <div style="background: var(--bg-subtle); padding: 1.15rem; border-radius: var(--radius-md); border: 1px solid var(--border-light); margin: 1.25rem 0;">
        <form method="get" action="{{ route('workspaces.plans.create',$workspace) }}" id="source-search" class="source-search" style="margin: 0;">
            <input type="hidden" name="project_id" value="{{ $project?->id }}">
            <input type="hidden" name="base_id" value="{{ $base?->id }}">
            <label for="source-query">🔍 Tìm công việc nguồn theo mã hoặc tên</label>
            <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                <input type="search" id="source-query" name="source_q" maxlength="200" value="{{ request('source_q') }}" placeholder="Nhập từ khóa tìm kiếm..." style="flex: 1; min-width: 220px;">
                <button type="submit" class="secondary">Tìm nguồn</button>
            </div>
            <p id="source-search-result" class="muted" aria-live="polite" style="font-size: 0.8rem; margin: 0.5rem 0 0 0;">
                Tối đa 200 kết quả mỗi lần tìm. Các task đã chọn trong bản nháp vẫn được bảo toàn.
            </p>
        </form>
    </div>

    <!-- Plan Editor Main Form -->
    <form method="post" action="{{ route('workspaces.plans.store',$workspace) }}" data-form="plan-create" data-dirty-form id="plan-editor">
        @csrf
        <input type="hidden" name="editor_enabled" value="0">
        <input type="hidden" name="payload" id="payload-packed" value="">
        <input type="hidden" name="project_id" value="{{ $project?->id }}">
        <input type="hidden" name="base_id" value="{{ $base?->id }}">

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 1rem; align-items: start; margin-bottom: 1.25rem;">
            <div>
                <x-input name="name" label="Tên kế hoạch" :value="$base?->name ?? 'Kế hoạch mới'" maxlength="180" required placeholder="Nhập tên kế hoạch..." />
            </div>
            <div>
                <label for="input-mode">Chế độ soạn thảo</label>
                <select name="input_mode" id="input-mode">
                    <option value="editor" @selected(($editorData['renderable']?old('input_mode','editor'):'json')==='editor')>Trình soạn trực quan (Visual Editor)</option>
                    <option value="json" @selected((!$editorData['renderable']||old('input_mode')==='json'))>JSON nâng cao (Schema V1)</option>
                </select>
            </div>
        </div>

        @if(!$editorData['renderable'])
            <div class="notice warning">
                Cấu trúc bản nhập cần chỉnh sửa trong JSON nâng cao. Nội dung gốc được giữ bên dưới, chưa có dữ liệu nào bị ghi đè.
            </div>
        @endif

        <div id="visual-editor">
            <div class="notice" style="background: var(--bg-subtle); border-color: var(--border);">
                <span>ℹ️</span>
                <div>Giới hạn: Tối đa 100 công việc, 300 quan hệ FS, 300 khung giờ, độ sâu cây 3 tầng. Mã ref được giữ ổn định khi đổi tên hoặc sắp xếp lại.</div>
            </div>

            <div id="editor-errors" class="notice errors" role="alert" tabindex="-1" hidden></div>
            <div class="editor-summary" id="editor-summary" aria-live="polite"></div>

            <!-- Section 1: Task Tree -->
            <section style="margin: 1.5rem 0; padding-top: 1rem; border-top: 1px solid var(--border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <div>
                        <h2 style="font-size: 1.2rem; margin: 0;">1. Cây công việc (Task Breakdown)</h2>
                        <p class="muted" style="margin: 0.25rem 0 0 0; font-size: 0.85rem;">Chọn công việc hiện có để cập nhật hoặc tạo mới. Chỉ công việc chưa bắt đầu mới được áp dụng thay đổi.</p>
                    </div>
                </div>

                <div id="editor-tasks">
                    @foreach(($editorData['payload']['tasks']??[]) as $index=>$row)
                        @include('plans.editor-task')
                    @endforeach
                </div>

                <div style="margin-top: 1rem;">
                    <button type="button" class="secondary" id="add-plan-task">
                        <span>+</span> Thêm công việc vào kế hoạch
                    </button>
                </div>
            </section>

            <!-- Section 2: Dependencies -->
            <section style="margin: 1.5rem 0; padding-top: 1.25rem; border-top: 1px solid var(--border);">
                <h2 style="font-size: 1.2rem; margin-bottom: 0.35rem;">2. Quan hệ chờ hoàn thành (FS Dependencies)</h2>
                <p class="muted" style="font-size: 0.85rem; margin-bottom: 0.75rem;">Xác định thứ tự thực hiện giữa các công việc.</p>
                <div id="editor-dependencies"></div>
                <div style="margin-top: 1rem;">
                    <button type="button" class="secondary" id="add-plan-dependency">
                        <span>+</span> Thêm quan hệ phụ thuộc
                    </button>
                </div>
            </section>

            <!-- Section 3: Slots -->
            <section style="margin: 1.5rem 0; padding-top: 1.25rem; border-top: 1px solid var(--border);">
                <h2 style="font-size: 1.2rem; margin-bottom: 0.35rem;">3. Khung giờ dự kiến (Calendar Slots)</h2>
                <p class="muted" style="font-size: 0.85rem; margin-bottom: 0.75rem;">
                    Giờ theo múi giờ <strong>{{ $workspace->timezone }}</strong>. Khung giờ chỉ được áp dụng cho chính người áp dụng và không được trùng lịch bận.
                </p>
                <div id="editor-slots"></div>
                <div style="margin-top: 1rem;">
                    <button type="button" class="secondary" id="add-plan-slot">
                        <span>+</span> Thêm khung giờ
                    </button>
                </div>
            </section>

            <noscript>
                <p class="notice warning">JavaScript đang tắt. Vui lòng chuyển cách soạn sang JSON nâng cao để gửi biểu mẫu.</p>
            </noscript>
        </div>

        <!-- Advanced JSON Panel -->
        <details id="json-panel" style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border);">
            <summary style="font-weight: 700; cursor: pointer;">JSON nâng cao / Dữ liệu chuyển giao Schema V1</summary>
            <div style="margin-top: 0.75rem;">
                <label for="payload">Payload JSON</label>
                <textarea id="payload" name="json_payload" rows="16" style="font-family: var(--font-mono); font-size: 0.85rem;">{{ old('payload',old('json_payload',$editorData['rawDraft'])) }}</textarea>
            </div>
        </details>

        <!-- Sticky Actions Bar -->
        <div class="sticky-actions" style="margin-top: 2rem;">
            <button type="submit" style="font-size: 0.95rem; padding: 0.65rem 1.5rem;">
                💾 Lưu bản nháp kế hoạch
            </button>
            <a href="{{ route('workspaces.plans.index',[$workspace,'project_id'=>$project?->id]) }}" class="button secondary">
                Quay lại danh sách
            </a>
            <span class="muted" style="font-size: 0.85rem; margin-left: 0.5rem;">
                Phê duyệt và áp dụng thực tế sẽ được thực hiện ở các bước tiếp theo.
            </span>
        </div>
    </form>

    <template id="plan-task-template">
        @include('plans.editor-task',['index'=>'__INDEX__','row'=>['ref'=>'','title'=>'','task_type'=>'WORK','priority'=>'medium']])
    </template>

    <script type="application/json" id="plan-editor-data">@json($editorData)</script>
    <script src="{{ asset('js/plan-editor.js') }}" defer></script>
</section>
</x-layout>
