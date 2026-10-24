<section class="card project-guide" aria-labelledby="project-guide-title" data-page="project-guide">
    <p class="eyebrow">Cách làm việc trong dự án</p><h2 id="project-guide-title">Từ phân công đến hoàn thành</h2>
    <p class="muted">Chọn bước phù hợp với công việc hiện tại. Các liên kết bên dưới hiển thị theo quyền của bạn.</p>
    <ol class="guide-grid">
        <li><strong>1. Chuẩn bị đội ngũ</strong><p>Thêm thành viên và giao vai trò trước khi phân công công việc.</p>@can('manageMembers',$project)<a href="{{ route('workspaces.projects.members.index',[$workspace,$project]) }}">Quản lý thành viên →</a>@else<p class="muted">Nhờ người quản lý dự án hỗ trợ thay đổi thành viên.</p>@endcan</li>
        <li><strong>2. Chia và giao việc</strong><p>Tạo nhóm, công việc và cột mốc; chỉ định người làm, người duyệt và hạn hoàn thành.</p>@if($canViewTasks)<a href="{{ route('workspaces.projects.tasks.tree',[$workspace,$project]) }}">Xem cây công việc →</a>@endif
        @if(app(\App\Policies\TaskPolicy::class)->projectWritable($project) && app(\App\Policies\TaskPolicy::class)->scope(auth()->user(),$workspace,$project,'task.create'))<a href="{{ route('workspaces.projects.tasks.create',[$workspace,$project]) }}">Tạo công việc →</a>@endif</li>
        <li><strong>3. Thực hiện và cập nhật</strong><p>Mở việc được giao, bắt đầu thực hiện, ghi giờ và báo vấn đề đang chặn nếu có.</p>@if($canViewTasks)<a href="{{ route('workspaces.projects.tasks.index',[$workspace,$project,'preset'=>'mine']) }}">Việc của tôi →</a>@endif</li>
        <li><strong>4. Duyệt và theo dõi</strong><p>Nộp duyệt sau khi hoàn tất. Người duyệt chấp thuận hoặc yêu cầu sửa kèm lý do.</p>@if($canViewTasks && app(\App\Policies\TaskPolicy::class)->scope(auth()->user(),$workspace,$project,'task.review'))<a href="{{ route('workspaces.projects.tasks.index',[$workspace,$project,'preset'=>'review']) }}">Chờ tôi duyệt →</a>@endif @if($canViewReport)<a href="{{ route('workspaces.dashboard',[$workspace,'project_id'=>$project->id]) }}">Xem báo cáo →</a>@endif</li>
    </ol>
</section>
