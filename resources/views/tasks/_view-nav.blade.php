@php($taskView=$taskView ?? ($filters['view']??'list'))
<nav class="view-switcher" aria-label="Cách xem công việc">
    <a href="{{ route($routePrefix.'.index',array_merge($routeParams,$filters??[],['view'=>'list'])) }}" @if($taskView==='list') aria-current="page" @endif>Danh sách</a>
    <a href="{{ route($routePrefix.'.index',array_merge($routeParams,$filters??[],['view'=>'board'])) }}" @if($taskView==='board') aria-current="page" @endif>Kanban</a>
    <a href="{{ route($routePrefix.'.tree',$routeParams) }}" @if($taskView==='tree') aria-current="page" @endif>Cây công việc</a>
</nav>
<p class="view-help muted">Danh sách và Kanban giữ bộ lọc khi chuyển cách xem. Cây hiển thị toàn bộ cấu trúc trong phạm vi bạn được xem.</p>
