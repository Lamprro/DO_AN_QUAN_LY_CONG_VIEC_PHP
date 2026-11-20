<x-layout title="Mẫu và công việc lặp">
<section class="card">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
        <div>
            <h1 style="margin: 0 0 0.5rem;">Mẫu và công việc lặp</h1>
            <p class="muted" style="margin: 0;">Lưu cấu trúc cây Group, Task và Sub-task thành mẫu độc lập. Hỗ trợ chọn theo phân tầng: chọn Group sẽ tự động tạo lại toàn bộ con cháu; hoặc chọn riêng lẻ từng Task/Sub-task độc lập.</p>
        </div>
    </div>

    @if($canManage)
        <details style="margin-top: 1rem; border: 1px solid var(--border); border-radius: 8px; padding: 1rem; background: var(--surface);">
            <summary style="font-weight: 700; cursor: pointer; color: var(--primary);">+ Tạo mẫu mới từ công việc hiện có</summary>
            <form method="post" action="{{ route('workspaces.templates.store', $workspace) }}" data-form="template-create" style="margin-top: 1.25rem;">
                @csrf
                <input type="hidden" name="project_id" value="{{ $project?->id }}">
                @include('templates._settings')

                <fieldset style="margin: 1.5rem 0; border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem;">
                    <legend style="font-weight: 700; padding: 0 0.5rem; color: var(--text);">
                        Phân cấp công việc mẫu (Group → Task → Sub-task)
                    </legend>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0 0 1rem;">
                        💡 <strong>Quy tắc phân tầng:</strong> Khi chọn Nhóm (Group), tất cả Task chuẩn và Sub-task con sẽ tự động được chọn và tạo lại. Bạn cũng có thể chọn riêng lẻ từng Task hoặc Sub-task độc lập mà không cần Group cha.
                    </p>

                    @php
                        $sourceNodes = $sources->groupBy(fn($t) => $t->parent_task_id ?? 'root');
                        $groups = $sources->where('task_type', \App\Enums\TaskType::GROUP);
                        $standaloneTasks = $sourceNodes->get('root', collect())->where('task_type', '!=', \App\Enums\TaskType::GROUP);
                    @endphp

                    @forelse($groups as $group)
                        @php
                            $childTasks = $sourceNodes->get($group->id, collect());
                        @endphp
                        <div class="template-group-box" style="border: 1px solid rgba(99, 102, 241, 0.3); border-radius: 8px; padding: 12px; margin-bottom: 12px; background: rgba(99, 102, 241, 0.03);">
                            <label style="display: flex; align-items: center; gap: 8px; font-weight: 750; font-size: 0.95rem; cursor: pointer; color: var(--text);">
                                <input type="checkbox" name="task_ids[]" value="{{ $group->id }}" data-group-checkbox="{{ $group->id }}" class="group-master-cb" @checked(in_array($group->id, old('task_ids', [])))>
                                <span>📁 {{ $group->code }} · {{ $group->title }}</span>
                                <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: var(--primary); font-size: 11px;">NHÓM CHA (EPIC)</span>
                            </label>
                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-left: 28px; margin-bottom: 8px;">
                                (Chọn nhóm này sẽ mặc định chọn và tạo lại toàn bộ Task và Sub-task bên trong)
                            </div>

                            @if($childTasks->isNotEmpty())
                                <div style="margin-left: 28px; border-left: 2px solid rgba(99, 102, 241, 0.2); padding-left: 14px; display: flex; flex-direction: column; gap: 8px;">
                                    @foreach($childTasks as $child)
                                        @php
                                            $subtasks = $sourceNodes->get($child->id, collect());
                                        @endphp
                                        <div style="background: var(--surface); padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border);">
                                            <label style="display: flex; align-items: center; gap: 8px; font-weight: 650; font-size: 0.9rem; cursor: pointer;">
                                                <input type="checkbox" name="task_ids[]" value="{{ $child->id }}" data-parent-group="{{ $group->id }}" data-task-checkbox="{{ $child->id }}" class="task-cb" @checked(in_array($child->id, old('task_ids', [])))>
                                                <span>📋 {{ $child->code }} · {{ $child->title }}</span>
                                                <span class="badge" style="font-size: 10px;">TASK (CẤP 2)</span>
                                            </label>

                                            @if($subtasks->isNotEmpty())
                                                <div style="margin-left: 26px; margin-top: 6px; display: flex; flex-direction: column; gap: 4px;">
                                                    @foreach($subtasks as $sub)
                                                        <label style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: var(--text-muted); cursor: pointer;">
                                                            <input type="checkbox" name="task_ids[]" value="{{ $sub->id }}" data-parent-group="{{ $group->id }}" data-parent-task="{{ $child->id }}" class="subtask-cb" @checked(in_array($sub->id, old('task_ids', [])))>
                                                            <span>↳ {{ $sub->code }} · {{ $sub->title }}</span>
                                                            <span class="badge" style="font-size: 10px;">SUB-TASK (CẤP 3)</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                    @endforelse

                    @if($standaloneTasks->isNotEmpty())
                        <div class="template-standalone-box" style="border: 1px solid var(--border); border-radius: 8px; padding: 12px; margin-bottom: 12px; background: var(--surface);">
                            <div style="font-weight: 750; font-size: 0.95rem; margin-bottom: 8px; color: var(--text);">
                                📋 Công việc độc lập / Chưa phân nhóm ({{ $standaloneTasks->count() }})
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                @foreach($standaloneTasks as $st)
                                    @php
                                        $subtasks = $sourceNodes->get($st->id, collect());
                                    @endphp
                                    <div style="padding: 6px 10px; border-radius: 6px; background: var(--bg-subtle);">
                                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer;">
                                            <input type="checkbox" name="task_ids[]" value="{{ $st->id }}" data-task-checkbox="{{ $st->id }}" class="task-cb" @checked(in_array($st->id, old('task_ids', [])))>
                                            <span>📋 {{ $st->code }} · {{ $st->title }}</span>
                                        </label>
                                        @if($subtasks->isNotEmpty())
                                            <div style="margin-left: 26px; margin-top: 4px; display: flex; flex-direction: column; gap: 4px;">
                                                @foreach($subtasks as $sub)
                                                    <label style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: var(--text-muted); cursor: pointer;">
                                                        <input type="checkbox" name="task_ids[]" value="{{ $sub->id }}" data-parent-task="{{ $st->id }}" class="subtask-cb" @checked(in_array($sub->id, old('task_ids', [])))>
                                                        <span>↳ {{ $sub->code }} · {{ $sub->title }}</span>
                                                        <span class="badge" style="font-size: 10px;">SUB-TASK (CẤP 3)</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($sources->isEmpty())
                        <p class="muted">Chưa có công việc nào trong dự án. Hãy tạo công việc trước khi lưu mẫu.</p>
                    @endif
                </fieldset>

                <button style="font-weight: 700;">Lưu mẫu công việc</button>
            </form>
        </details>
    @endif
</section>

@forelse($templates as $t)
    <article class="card" style="margin-top: 1rem;">
        <h2><a href="{{ route('workspaces.templates.show', [$workspace, $t->id]) }}">{{ $t->name }}</a></h2>
        <p>{{ ['active'=>'Đang lặp','paused'=>'Tạm dừng / thủ công','archived'=>'Đã lưu trữ'][$t->status->value] }} · {{ count($t->definition['tasks']) }} task · {{ $t->frequency ? \App\Support\UiLabels::text($t->frequency) : 'thủ công' }}</p>
        <p>Lần tới: {{ $t->next_run_at?->setTimezone($t->timezone)->format('d/m/Y H:i') ?? 'Không có lịch tự động' }} · {{ $t->timezone }}</p>
        @if($t->last_error)
            <p class="notice warning">{{ $t->last_error }}</p>
        @endif
    </article>
@empty
    <section class="card" style="margin-top: 1rem;">Chưa có mẫu trong phạm vi này.</section>
@endforelse
{{ $templates->links() }}

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Tự động tick con khi tick Group
    document.querySelectorAll('.group-master-cb').forEach(function (gcb) {
        gcb.addEventListener('change', function () {
            var gId = this.getAttribute('data-group-checkbox');
            document.querySelectorAll('[data-parent-group="' + gId + '"]').forEach(function (child) {
                child.checked = gcb.checked;
            });
        });
    });

    // Tự động tick subtask khi tick Task
    document.querySelectorAll('.task-cb').forEach(function (tcb) {
        tcb.addEventListener('change', function () {
            var tId = this.getAttribute('data-task-checkbox');
            document.querySelectorAll('[data-parent-task="' + tId + '"]').forEach(function (sub) {
                sub.checked = tcb.checked;
            });
        });
    });
});
</script>
</x-layout>
