<fieldset class="editor-task card" data-task-row><legend>Công việc <span data-row-ref>{{ $row['ref']??'' }}</span></legend><div class="actions"><button type="button" class="secondary" data-move="up">Lên</button><button type="button" class="secondary" data-move="down">Xuống</button><button type="button" class="danger" data-remove-task>Bỏ khỏi đề xuất</button></div>
<div class="form-grid"><div><label for="task-ref-{{ $index }}">Ref cố định</label><input id="task-ref-{{ $index }}" name="editor[tasks][{{ $index }}][ref]" data-field="ref" value="{{ $row['ref']??'' }}" readonly required></div>
<div><label for="source-{{ $index }}">Nguồn công việc</label><select id="source-{{ $index }}" name="editor[tasks][{{ $index }}][task_id]" data-field="task_id"><option value="">Tạo công việc mới</option>
@foreach($editorData['tasks'] as $choice)
<option value="{{ $choice['id'] }}" @selected(($row['task_id']??null)===$choice['id']) @disabled(!$choice['editable']&&($row['task_id']??null)!==$choice['id'])>{{ $choice['label'] }}{{ !$choice['editable']?' (không sửa được)':'' }}</option>
@endforeach
</select></div>
<div><label for="task-title-{{ $index }}">Tiêu đề (bắt buộc)</label><input id="task-title-{{ $index }}" name="editor[tasks][{{ $index }}][title]" data-field="title" value="{{ $row['title']??'' }}" required maxlength="180"></div>
<div><label for="type-{{ $index }}">Loại</label><select id="type-{{ $index }}" name="editor[tasks][{{ $index }}][task_type]" data-field="task_type">
@foreach(['GROUP'=>'Nhóm việc','WORK'=>'Công việc','MILESTONE'=>'Mốc'] as $v=>$label)
<option value="{{ $v }}" @selected(($row['task_type']??'WORK')===$v)>{{ $label }}</option>
@endforeach
</select></div>
<div><label for="parent-{{ $index }}">Nhóm cha</label><select id="parent-{{ $index }}" name="editor[tasks][{{ $index }}][parent_ref]" data-field="parent_ref" data-current="{{ $row['parent_ref']??'' }}"><option value="">Không có</option>@if(!empty($row['parent_ref']))<option value="{{ $row['parent_ref'] }}" selected>{{ $row['parent_ref'] }}</option>@endif</select></div>
<div><label for="priority-{{ $index }}">Ưu tiên</label><select id="priority-{{ $index }}" name="editor[tasks][{{ $index }}][priority]" data-field="priority">
@foreach(['low','medium','high','urgent'] as $v)
<option value="{{ $v }}" @selected(($row['priority']??'medium')===$v)>{{ \App\Support\UiLabels::text($v) }}</option>
@endforeach
</select></div>
<div data-work-only><label for="estimate-{{ $index }}">Ước lượng (phút)</label><input type="number" min="0" max="525600" id="estimate-{{ $index }}" name="editor[tasks][{{ $index }}][estimate_minutes]" data-field="estimate_minutes" value="{{ $row['estimate_minutes']??'' }}"></div>
@foreach(['assignee_id'=>'Người thực hiện','reviewer_id'=>'Người duyệt'] as $field=>$label)
<div data-assignment><label for="{{ $field }}-{{ $index }}">{{ $label }}</label><select id="{{ $field }}-{{ $index }}" name="editor[tasks][{{ $index }}][{{ $field }}]" data-field="{{ $field }}"><option value="">Chưa phân công</option>
@if(!empty($row[$field]) && !in_array($row[$field],array_column($editorData[$field==='reviewer_id'?'reviewers':'users'],'id'),true))<option value="{{ $row[$field] }}" selected>Giữ người cũ / cần kiểm tra điều kiện</option>@endif
@foreach($editorData[$field==='reviewer_id'?'reviewers':'users'] as $person)
<option value="{{ $person['id'] }}" @selected(($row[$field]??null)===$person['id'])>{{ $person['label'] }}</option>
@endforeach
</select></div>
@endforeach
</div><label for="description-{{ $index }}">Mô tả</label><textarea id="description-{{ $index }}" rows="3" maxlength="10000" name="editor[tasks][{{ $index }}][description]" data-field="description">{{ $row['description']??'' }}</textarea>
</fieldset>
