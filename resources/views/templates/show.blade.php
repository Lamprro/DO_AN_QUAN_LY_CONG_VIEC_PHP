<x-layout :title="$template->name"><section class="card"><h1>{{ $template->name }}</h1><a href="{{ route('workspaces.templates.index',[$workspace,'project_id'=>$template->project_id]) }}">Danh sách mẫu</a><p>Trạng thái: {{ ['active'=>'Đang chạy lịch lặp','paused'=>'Tạm dừng / tạo thủ công','archived'=>'Đã lưu trữ'][$template->status->value] }} · Múi giờ: {{ $template->timezone }} · Kỳ tới: {{ $template->next_run_at?->setTimezone($template->timezone)->format('d/m/Y H:i')??'Không tự chạy' }}</p>@if($template->last_error)<p class="notice warning">{{ $template->last_error }}</p>@endif
<h2>Cấu trúc phân tầng mẫu (Group → Task → Sub-task)</h2>
<div style="border: 1px solid var(--border); border-radius: 8px; padding: 1rem; margin: 1rem 0; background: var(--surface);">
    @php
        $tasksInDef = collect($template->definition['tasks']);
        $tasksByParent = $tasksInDef->groupBy(fn($row) => $row['parent_ref'] ?? 'root');
        $groupRows = $tasksInDef->where('task_type', 'GROUP');
        $standaloneRows = $tasksByParent->get('root', collect())->where('task_type', '!=', 'GROUP');
    @endphp

    @forelse($groupRows as $gRow)
        <div style="margin-bottom: 1rem; padding: 10px 14px; border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 8px; background: rgba(99, 102, 241, 0.04);">
            <div style="font-weight: 750; font-size: 1rem; display: flex; align-items: center; gap: 8px;">
                <span>📁 {{ $gRow['title'] }}</span>
                <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: var(--primary); font-size: 11px;">NHÓM CHA (EPIC)</span>
                <span class="badge" style="font-size: 11px;">{{ \App\Support\UiLabels::text($gRow['priority']) }}</span>
            </div>
            @php $childInDef = $tasksByParent->get($gRow['ref'], collect()); @endphp
            @if($childInDef->isNotEmpty())
                <div style="margin-left: 20px; margin-top: 8px; border-left: 2px solid rgba(99, 102, 241, 0.2); padding-left: 14px; display: flex; flex-direction: column; gap: 6px;">
                    @foreach($childInDef as $cRow)
                        @php $subsInDef = $tasksByParent->get($cRow['ref'], collect()); @endphp
                        <div>
                            <div style="font-weight: 650; font-size: 0.9rem; display: flex; align-items: center; gap: 6px;">
                                <span>📋 {{ $cRow['title'] }}</span>
                                <span class="badge" style="font-size: 10px;">TASK (CẤP 2)</span>
                                <span style="font-size: 0.8rem; color: var(--text-muted);">{{ $cRow['estimate_minutes'] ?? 0 }} phút</span>
                            </div>
                            @if($subsInDef->isNotEmpty())
                                <div style="margin-left: 20px; margin-top: 4px; display: flex; flex-direction: column; gap: 4px;">
                                    @foreach($subsInDef as $sRow)
                                        <div style="font-size: 0.85rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                                            <span>↳ {{ $sRow['title'] }}</span>
                                            <span class="badge" style="font-size: 9px;">SUB-TASK (CẤP 3)</span>
                                            <span style="font-size: 0.75rem;">{{ $sRow['estimate_minutes'] ?? 0 }} phút</span>
                                        </div>
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

    @if($standaloneRows->isNotEmpty())
        <div style="padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-subtle);">
            <div style="font-weight: 750; font-size: 0.95rem; margin-bottom: 6px; color: var(--text);">
                📋 Công việc độc lập / Chưa phân nhóm ({{ $standaloneRows->count() }})
            </div>
            <div style="display: flex; flex-direction: column; gap: 6px;">
                @foreach($standaloneRows as $sRow)
                    @php $subsInDef = $tasksByParent->get($sRow['ref'], collect()); @endphp
                    <div>
                        <div style="font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 6px;">
                            <span>📋 {{ $sRow['title'] }}</span>
                            <span class="badge" style="font-size: 10px;">TASK (CẤP 2)</span>
                            <span style="font-size: 0.8rem; color: var(--text-muted);">{{ $sRow['estimate_minutes'] ?? 0 }} phút</span>
                        </div>
                        @if($subsInDef->isNotEmpty())
                            <div style="margin-left: 20px; margin-top: 4px; display: flex; flex-direction: column; gap: 4px;">
                                @foreach($subsInDef as $sub)
                                    <div style="font-size: 0.85rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                                        <span>↳ {{ $sub['title'] }}</span>
                                        <span class="badge" style="font-size: 9px;">SUB-TASK (CẤP 3)</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
<p>{{ count($template->definition['dependencies']) }} dependency FS được ánh xạ sang UUID mới khi tạo.</p>
@if($canManage&&$template->status->value!=='archived')<form method="post" action="{{ route('workspaces.templates.instantiate',[$workspace,$template->id]) }}" data-form="template-instantiate">@csrf<input type="hidden" name="revision" value="{{ $template->revision }}"><input type="hidden" name="request_key" value="{{ $requestKey }}"><button>Tạo bộ công việc mới từ mẫu</button></form>
<div class="actions">@foreach(($template->status->value==='active'?['pause'=>'Tạm dừng','archive'=>'Lưu trữ mẫu']:($template->frequency?['resume'=>'Tiếp tục lịch lặp','archive'=>'Lưu trữ mẫu']:['archive'=>'Lưu trữ mẫu'])) as $action=>$label)<form method="post" action="{{ route('workspaces.templates.state',[$workspace,$template->id,$action]) }}" data-form="template-{{ $action }}">@csrf<input type="hidden" name="revision" value="{{ $template->revision }}"><button class="secondary">{{ $label }}</button></form>@endforeach</div>
<details><summary>Sửa tên và lịch lặp</summary><form method="post" action="{{ route('workspaces.templates.update',[$workspace,$template->id]) }}" data-form="template-update">@csrf @method('PUT')<input type="hidden" name="revision" value="{{ old('revision',$template->revision) }}">@include('templates._settings')<button>Lưu cấu hình</button></form></details>@endif</section>
<section class="card"><h2>Lịch sử tạo việc</h2><p>Kỳ bị lỡ chỉ tạo một bộ khi scheduler hoạt động lại, sau đó chuyển sang kỳ tương lai; không tạo hàng loạt mọi kỳ đã bỏ lỡ. Ngày DST không có/giờ lặp được bỏ qua, không tự đổi sang giờ khác.</p>
@forelse($runs as $run)<article><h3>{{ $run->status->value==='succeeded'?'Đã tạo thành công':'Kỳ không tạo được' }}</h3><p>{{ $run->created_at->format('d/m/Y H:i') }} UTC · {{ str_starts_with($run->occurrence_key,'manual:')?'Tạo thủ công':'Chạy theo lịch lặp' }}</p>@if($run->error)<p class="notice warning">{{ $run->error }}</p>@endif<ul>@foreach($run->task_map??[] as $id)<li>@if(isset($generatedTasks[$id]))<a href="{{ $template->project_id?route('workspaces.projects.tasks.show',[$workspace,$template->project_id,$id]):route('workspaces.tasks.show',[$workspace,$id]) }}">{{ $generatedTasks[$id]->code }} — {{ $generatedTasks[$id]->title }}</a>@else Công việc không còn truy cập được. @endif</li>@endforeach</ul></article>@empty<p>Chưa có lần tạo.</p>@endforelse{{ $runs->links() }}</section></x-layout>
