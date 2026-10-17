<x-layout :title="$project->name"><section class="card" data-page="project-show" data-project-id="{{ $project->id }}">
<a href="{{ route('workspaces.projects.index',$workspace) }}">Danh sách project</a><h1>{{ $project->name }}</h1>
<p>{{ \App\Support\UiLabels::text($project->status->value) }}
@if($project->archived_at) · Đã lưu trữ
@endif</p><p style="white-space:pre-wrap">{{ $project->description }}</p>
<p>Ngày bắt đầu: {{ $project->start_date?->format('d/m/Y') ?? 'Chưa có' }} · Hạn: {{ $project->deadline?->format('d/m/Y') ?? 'Chưa có' }}</p>
<p>Người tạo: {{ $project->creator->name }} · {{ $project->members_count }} membership · {{ $project->tasks_count ?? 'Không có quyền xem' }} task</p>
@if($parent)<p>Project cha: <a href="{{ route('workspaces.projects.show',[$workspace,$parent]) }}">{{ $parent->name }}</a></p>
@endif
<p class="notice">Quyền project con độc lập. Lưu trữ project ngăn sửa công việc, lịch và kế hoạch. Trạng thái Hoàn thành/Đã hủy ngăn ghi các dữ liệu này; nội dung vẫn được đọc theo quyền hiện tại.</p><div class="actions">
@if(app(\App\Support\ProjectAccess::class)->allows(auth()->user(),$project,'integration.view'))<a href="{{ route('workspaces.projects.github.show',[$workspace,$project]) }}">GitHub logs</a>
@endif
@if(app(\App\Services\PlanService::class)->allowed(auth()->user(),$workspace,$project ?? null))<a href="{{ route('workspaces.plans.index',[$workspace,'project_id'=>($project ?? null)?->id]) }}">Kế hoạch</a>
@endif
@if($canViewReport)<a class="button secondary" href="{{ route('workspaces.dashboard',[$workspace,'project_id'=>$project->id]) }}">Dashboard / báo cáo</a>
@endif
@if($canViewCalendar)<a class="button secondary" href="{{ route('workspaces.projects.timeline',[$workspace,$project]) }}">Timeline</a>
@endif
@can('update',$project)<a class="button" href="{{ route('workspaces.projects.edit',[$workspace,$project]) }}">Sửa project</a>
@endcan
@can('manageMembers',$project)<a class="button" href="{{ route('workspaces.projects.members.index',[$workspace,$project]) }}">Thành viên project</a>
@endcan
@if($canViewTasks)<a class="button" href="{{ route('workspaces.projects.tasks.index',[$workspace,$project]) }}">Công việc</a>
@endif</div></section>
<section class="card"><h2>Project con bạn được xem</h2>
@forelse($children as $child)<p><a href="{{ route('workspaces.projects.show',[$workspace,$child]) }}">{{ $child->name }}</a></p>
@empty<p>Không có project con trong phạm vi được xem.</p>
@endforelse</section>
@can('archive',$project)<section class="card"><h2>Lưu trữ / khôi phục</h2><form method="POST" action="{{ route($project->archived_at?'workspaces.projects.restore':'workspaces.projects.archive',[$workspace,$project]) }}" data-form="project-archive" data-confirm="Xác nhận thay đổi lưu trữ project này?">
@csrf<button type="submit">{{ $project->archived_at?'Khôi phục':'Lưu trữ project' }}</button></form></section>
@endcan
@can('delete',$project)<section class="card"><p>Chỉ xóa project nháp trống, không có dữ liệu liên quan.</p><form method="POST" action="{{ route('workspaces.projects.destroy',[$workspace,$project]) }}" data-form="project-delete" data-confirm="Xóa vĩnh viễn project nháp trống này?">
@csrf
@method('DELETE')<button class="danger" type="submit">Xóa project</button></form></section>
@endcan</x-layout>
