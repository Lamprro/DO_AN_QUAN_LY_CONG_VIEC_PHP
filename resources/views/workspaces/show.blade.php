<x-layout :title="$workspace->name"><section class="card" data-page="workspace-show" data-workspace-id="{{ $workspace->id }}"><h1>{{ $workspace->name }}</h1><p><span class="badge">{{ $workspace->kind->value }}</span> · Vai trò của bạn: <strong>{{ $membership->role->name }}</strong></p><p>Chủ sở hữu: {{ $workspace->owner->name }} · Múi giờ: {{ $workspace->timezone }}</p><p>{{ $workspace->members_count }} thành viên · {{ $workspace->roles_count }} vai trò · {{ $workspace->projects_count }} project · {{ $workspace->tasks_count }} task</p>
<div class="actions">
@if(app(\App\Services\PlanService::class)->allowed(auth()->user(),$workspace,$project ?? null))<a href="{{ route('workspaces.plans.index',[$workspace,'project_id'=>($project ?? null)?->id]) }}">Kế hoạch</a>
@endif
@if($canViewReport)<a class="button secondary" href="{{ route('workspaces.dashboard',$workspace) }}">Dashboard / báo cáo</a>
@endif
@if($canViewCalendar)<a class="button secondary" href="{{ route('workspaces.calendar.index',$workspace) }}">Lịch</a>
@endif<a class="button secondary" href="{{ route('workspaces.projects.index',$workspace) }}">Projects</a>
@if($workspace->kind->value==='personal')<a class="button" href="{{ route('workspaces.tasks.index',$workspace) }}">Công việc cá nhân</a>
@endif
@can('update',$workspace)<a class="button secondary" href="{{ route('workspaces.edit',$workspace) }}">Sửa workspace</a>
@endcan
@can('manageRoles',$workspace)<a class="button secondary" href="{{ route('workspaces.roles.index',$workspace) }}">Quản lý vai trò</a>
@endcan
@can('manageMembers',$workspace)<a class="button" href="{{ route('workspaces.members.index',$workspace) }}">Quản lý thành viên</a>
@endcan</div></section>
@if($workspace->owner_id === auth()->id() && $workspace->kind->value === 'team')<section class="card"><h2>Chuyển chủ sở hữu</h2><p class="muted">Chủ cũ chuyển thành Manager. Project con vẫn có phân quyền riêng.</p><form action="{{ route('workspaces.transfer',$workspace) }}" method="POST" data-form="owner-transfer">
@csrf<label for="member_id">Thành viên đang hoạt động</label><select id="member_id" name="member_id" required><option value="">Chọn người nhận</option>
@foreach($transferCandidates as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->user->name }} — {{ $candidate->user->email }}</option>
@endforeach</select><p><button type="submit">Chuyển chủ sở hữu</button></p></form></section>
<section class="card"><h2>Xóa workspace trống</h2><p class="muted">Chỉ xóa khi chưa có dữ liệu nghiệp vụ và không có lời mời đang chờ.</p><form action="{{ route('workspaces.destroy',$workspace) }}" method="POST" data-form="workspace-delete">
@csrf
@method('DELETE')<button class="danger" type="submit">Xóa workspace</button></form></section>
@elseif($workspace->owner_id !== auth()->id())<section class="card"><form action="{{ route('workspaces.leave',$workspace) }}" method="POST" data-form="workspace-leave">
@csrf<button class="danger" type="submit">Rời workspace</button></form></section>
@endif</x-layout>
