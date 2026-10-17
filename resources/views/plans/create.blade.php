<x-layout title="Soạn kế hoạch"><section class="card" data-page="plan-create"><div class="page-heading"><div><p class="eyebrow">{{ $project?->name ?? $workspace->name }} · {{ $workspace->timezone }}</p><h1>{{ $base?'Tạo phiên bản kế tiếp':'Tạo kế hoạch' }}</h1><p>Soạn công việc, quan hệ và lịch. Lưu nháp chưa thay đổi công việc hoặc lịch thật.</p></div></div>
<form method="get" action="{{ route('workspaces.plans.create',$workspace) }}" id="source-search" class="source-search"><input type="hidden" name="project_id" value="{{ $project?->id }}"><input type="hidden" name="base_id" value="{{ $base?->id }}"><label for="source-query">Tìm công việc nguồn theo mã / tên</label><input type="search" id="source-query" name="source_q" maxlength="200" value="{{ request('source_q') }}"><button>Tìm nguồn</button><p id="source-search-result" class="muted" aria-live="polite">Tối đa 200 kết quả mỗi lần tìm. Các task đã chọn vẫn được giữ; tìm kiếm không lưu kế hoạch.</p></form>
<form method="post" action="{{ route('workspaces.plans.store',$workspace) }}" data-form="plan-create" data-dirty-form id="plan-editor">
@csrf
<input type="hidden" name="editor_enabled" value="0"><input type="hidden" name="payload" id="payload-packed" value=""><input type="hidden" name="project_id" value="{{ $project?->id }}"><input type="hidden" name="base_id" value="{{ $base?->id }}">
<x-input name="name" label="Tên kế hoạch" :value="$base?->name ?? 'Kế hoạch mới'" maxlength="180" required />
<label for="input-mode">Cách soạn</label><select name="input_mode" id="input-mode"><option value="editor" @selected(($editorData['renderable']?old('input_mode','editor'):'json')==='editor')>Trình soạn công việc</option><option value="json" @selected((!$editorData['renderable']||old('input_mode')==='json'))>JSON nâng cao</option></select>
@if(!$editorData['renderable'])<p class="notice warning">Cấu trúc bản nhập cần sửa trong JSON nâng cao. Nội dung gốc được giữ bên dưới, chưa có dữ liệu nào được lưu.</p>@endif
<div id="visual-editor"><p class="muted">Tối đa 100 công việc, 300 quan hệ, 300 khung giờ, cây 3 tầng. Ref giữ ổn định khi đổi tên và thứ tự.</p><div id="editor-errors" class="notice errors" role="alert" tabindex="-1" hidden></div>
<div class="editor-summary" id="editor-summary" aria-live="polite"></div>
<section><h2>1. Cây công việc</h2><p>Chọn công việc hiện có để cập nhật, hoặc tạo mới. Chỉ công việc chưa bắt đầu mới được áp dụng thay đổi.</p><div id="editor-tasks">
@foreach(($editorData['payload']['tasks']??[]) as $index=>$row)
@include('plans.editor-task')
@endforeach
</div><button type="button" class="secondary" id="add-plan-task">Thêm công việc</button></section>
<section><h2>2. Quan hệ chờ hoàn thành (FS)</h2><div id="editor-dependencies"></div><button type="button" class="secondary" id="add-plan-dependency">Thêm quan hệ</button></section>
<section><h2>3. Khung giờ dự kiến</h2><p>Giờ theo {{ $workspace->timezone }}. Slot chỉ được áp dụng cho chính người áp dụng và không được trùng lịch bận.</p><div id="editor-slots"></div><button type="button" class="secondary" id="add-plan-slot">Thêm khung giờ</button></section>
<noscript><p class="notice warning">JavaScript đang tắt. Chuyển cách soạn sang JSON nâng cao để lưu khi không có JavaScript. Trình soạn trực quan cần JavaScript; hệ thống không tự bỏ các quan hệ/slot đã có.</p></noscript></div>
<details id="json-panel"><summary>JSON nâng cao / dữ liệu chuyển giao</summary><label for="payload">Payload JSON (chỉ dùng khi chọn JSON nâng cao)</label><textarea id="payload" name="json_payload" rows="18">{{ old('payload',old('json_payload',$editorData['rawDraft'])) }}</textarea></details>
<div class="sticky-actions"><button type="submit">Lưu bản nháp</button><a href="{{ route('workspaces.plans.index',[$workspace,'project_id'=>$project?->id]) }}">Về danh sách</a><span class="muted">Duyệt và áp dụng ở bước tiếp theo.</span></div>
</form>
<template id="plan-task-template">@include('plans.editor-task',['index'=>'__INDEX__','row'=>['ref'=>'','title'=>'','task_type'=>'WORK','priority'=>'medium']])</template>
<script type="application/json" id="plan-editor-data">@json($editorData)</script><script src="{{ asset('js/plan-editor.js') }}" defer></script>
</section></x-layout>
