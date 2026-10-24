@php($currentView=$filters['view']??'list')
<nav class="preset-bar" aria-label="Bộ lọc công việc nhanh">
@foreach(['all'=>'Tất cả việc','mine'=>'Việc của tôi','review'=>'Chờ tôi duyệt','blocked'=>'Đang bị chặn','overdue'=>'Quá hạn'] as $value=>$label)
    <a class="preset-chip {{ ($filters['preset']??'all')===$value ? 'active' : '' }}" href="{{ route($routePrefix.'.index',array_merge($routeParams,['view'=>$currentView,'preset'=>$value])) }}" @if(($filters['preset']??'all')===$value) aria-current="true" @endif>{{ $label }}</a>
@endforeach
</nav>
<form method="GET" action="{{ route($routePrefix.'.index',$routeParams) }}" class="card task-filter-panel" data-form="task-search">
    <input type="hidden" name="view" value="{{ $currentView }}">
    <input type="hidden" name="preset" data-filter-label="Bộ lọc nhanh" data-filter-value="{{ ['all'=>'Tất cả việc','mine'=>'Việc của tôi','review'=>'Chờ tôi duyệt','blocked'=>'Đang bị chặn','overdue'=>'Quá hạn'][$filters['preset']??'all'] }}" value="{{ $filters['preset']??'all' }}">
    <div class="filter-primary">
        <div><label for="q">Tìm tiêu đề hoặc mã</label><input type="search" id="q" name="q" value="{{ $filters['q']??'' }}" maxlength="200" placeholder="Nhập tên hoặc mã công việc…"></div>
        <div><label for="status">Trạng thái</label><select id="status" name="status"><option value="">Tất cả trạng thái</option>@foreach(\App\Enums\TaskStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status']??'')===$status->value)>{{ \App\Support\UiLabels::text($status) }}</option>@endforeach</select></div>
        <div class="actions"><button type="submit">Áp dụng bộ lọc</button><a href="{{ route($routePrefix.'.index',array_merge($routeParams,['view'=>$currentView])) }}" class="button secondary" data-filter-reset>Xóa bộ lọc</a></div>
    </div>
    <details class="advanced-filters" @if(!empty($filters['priority']) || !empty($filters['assignee_id']) || ($filters['archived']??'active')!=='active' || ($filters['sort']??'created_at')!=='created_at' || ($filters['direction']??'desc')!=='desc' || (int)($filters['per_page']??10)!==10) open @endif>
        <summary>Lọc thêm: người làm, ưu tiên, lưu trữ và sắp xếp</summary>
        <div class="filter-grid">
            <div><label for="priority">Mức ưu tiên</label><select id="priority" name="priority"><option value="">Tất cả ưu tiên</option>@foreach(\App\Enums\TaskPriority::cases() as $priority)<option value="{{ $priority->value }}" @selected(($filters['priority']??'')===$priority->value)>{{ \App\Support\UiLabels::text($priority) }}</option>@endforeach</select></div>
            <div><label for="assignee_id">Người thực hiện</label><select id="assignee_id" name="assignee_id"><option value="">Tất cả thành viên</option>@foreach($people as $person)<option value="{{ $person->user_id }}" @selected(($filters['assignee_id']??'')===$person->user_id)>{{ $person->user->name }}</option>@endforeach</select></div>
            @include('shared.list-controls',['sortOptions'=>['created_at'=>'Ngày tạo','code'=>'Mã công việc','title'=>'Tiêu đề','priority'=>'Mức ưu tiên','deadline_at'=>'Hạn hoàn thành']])
            <div><label for="per_page">Số dòng ở danh sách</label><select id="per_page" name="per_page">@foreach([10,25,50] as $n)<option value="{{ $n }}" @selected((int)($filters['per_page']??10)===$n)>{{ $n }} dòng</option>@endforeach</select></div>
        </div>
    </details>
    <p class="muted filter-help">Bộ lọc nhanh đặt lại các điều kiện khác. Kanban hiển thị tối đa 10 thẻ mỗi cột; nhóm việc nằm trong danh sách và cây. Hạn giờ theo {{ $workspace->timezone }}.</p>
</form>
