
@php($utilityPrefix=$project?'workspaces.projects':'workspaces')
<section class="card"><h2>Tiện ích công việc</h2><div class="actions">
@foreach(['all'=>'Tất cả','mine'=>'Việc của tôi','review'=>'Chờ tôi duyệt','blocked'=>'Đang bị chặn','overdue'=>'WORK quá hạn'] as $value=>$label)<a class="button secondary" href="{{ route($routePrefix.'.index',array_merge($routeParams,['preset'=>$value])) }}">{{ $label }}</a>
@endforeach</div>
<p>Danh sách nhanh áp dụng trên toàn bộ công việc trong phạm vi, không chỉ trang đang xem; có thể kết hợp các bộ lọc khác. Quá hạn chỉ WORK đang mở; điều kiện hiện tại không chứng minh nguyên nhân trễ trong quá khứ.</p>
<form method="post" action="{{ route($utilityPrefix.'.task-filters.store',$routeParams) }}" data-form="filter-save">
@csrf
@foreach($filters as $key=>$value)<input type="hidden" name="query[{{ $key }}]" value="{{ $value }}">
@endforeach
@if(!$filters)<input type="hidden" name="query[preset]" value="all">
@endif<label for="filter-name">Lưu bộ lọc đang áp dụng (riêng bạn, tối đa20/phạm vi)</label><input name="name" id="filter-name" maxlength="80" required><button>Lưu bộ lọc</button></form>
@foreach($savedFilters as $saved)<div class="actions"><a href="{{ route($utilityPrefix.'.task-filters.open',[...$routeParams,$saved]) }}">{{ $saved->name }}</a><form method="post" action="{{ route($utilityPrefix.'.task-filters.destroy',[...$routeParams,$saved]) }}" data-form="filter-delete">
@csrf
@method('DELETE')<button class="secondary">Xóa bộ lọc</button></form></div>
@endforeach</section>
<details class="card"><summary>Thao tác hàng loạt trên các dòng được chọn</summary><p>Tối đa50 việc, cùng phạm vi. Một việc lỗi, mất quyền hoặc dữ liệu cũ sẽ hủy toàn bộ thay đổi của nhóm. Không duyệt/DONE hàng loạt. GROUP có con chưa lưu trữ phải xử lý con trước.</p><form id="bulk-tasks" method="post" action="{{ route($routePrefix.'.bulk',$routeParams) }}" data-form="task-bulk" data-confirm="Lưu cả nhóm đã chọn? Một lỗi sẽ hủy toàn bộ thay đổi nhóm này.">
@csrf<label for="bulk-action">Thao tác</label><select id="bulk-action" name="action"><option value="priority">Đổi ưu tiên</option>
@if($canAssign)<option value="assign">Giao/đổi người làm</option>
@endif<option value="archive">Lưu trữ</option></select><label for="bulk-priority">Ưu tiên mới</label><select id="bulk-priority" name="priority">
@foreach(\App\Enums\TaskPriority::cases() as $priority)<option value="{{ $priority->value }}">{{ \App\Support\UiLabels::text($priority) }}</option>
@endforeach</select><label for="bulk-assignee">Người làm (chỉ thao tác phân công)</label><select id="bulk-assignee" name="assignee_id"><option value="">Bỏ phân công</option>
@foreach($people as $person)<option value="{{ $person->user_id }}">{{ $person->user->name }}</option>
@endforeach</select><button>Thực hiện nhóm đã chọn</button></form></details>
