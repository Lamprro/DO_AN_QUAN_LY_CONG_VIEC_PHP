<x-layout title="Projects"><h1>Projects — {{ $workspace->name }}</h1>
<div class="actions"><a href="{{ route('workspaces.show',$workspace) }}">Về workspace</a>
@if($canCreate)<a class="button" href="{{ route('workspaces.projects.create',$workspace) }}">Tạo project</a>
@endif</div>
<form class="card" method="GET" action="{{ route('workspaces.projects.index',$workspace) }}" data-form="project-search">
<x-input name="q" label="Tìm tên project" :value="$filters['q'] ?? ''" />
<label for="status">Trạng thái</label><select name="status" id="status"><option value="">Tất cả</option>
@foreach(\App\Enums\ProjectStatus::cases() as $s)<option value="{{ $s->value }}"
@selected(($filters['status']??'')===$s->value)>{{ \App\Support\UiLabels::text($s) }}</option>
@endforeach</select>
@include('shared.list-controls',['sortOptions'=>['created_at'=>'Ngày tạo','name'=>'Tên','deadline'=>'Hạn hoàn thành']])
<label for="project-view">Hiển thị</label><select id="project-view" name="view"><option value="cards"
@selected(($filters['view']??'cards')==='cards')>Thẻ</option><option value="table"
@selected(($filters['view']??'cards')==='table')>Bảng</option></select><button type="submit">Lọc</button></form>
@if(($filters['view']??'cards')==='table')
<section class="card table-wrap mobile-data-table" data-page="projects-index"><table><thead><tr><th>Project</th><th>Trạng thái</th><th>Công việc</th><th>Hạn</th></tr></thead><tbody>
@forelse($projects as $project)<tr><td data-label="Project"><a href="{{ route('workspaces.projects.show',[$workspace,$project]) }}">{{ $project->name }}</a></td><td data-label="Trạng thái"><x-status :value="$project->status" /> {{ $project->archived_at?'Đã lưu trữ':'' }}</td><td data-label="Công việc">{{ $project->tasks_count ?? 'Không có quyền xem' }}</td><td data-label="Hạn">{{ $project->deadline?->format('d/m/Y')??'Chưa có' }}</td></tr>
@empty<tr><td colspan="4">Không có project phù hợp bộ lọc.</td></tr>
@endforelse
</tbody></table></section>
@else
<section class="grid" data-page="projects-index">
@forelse($projects as $project)<article class="card"><h2><a href="{{ route('workspaces.projects.show',[$workspace,$project]) }}">{{ $project->name }}</a></h2><p>{{ \App\Support\UiLabels::text($project->status->value) }} · {{ $project->tasks_count ?? 'Không có quyền xem' }} task
@if($project->archived_at) · Đã lưu trữ
@endif</p><p>Hạn: {{ $project->deadline?->format('d/m/Y') ?? 'Chưa có' }}</p></article>
@empty<p>Chưa có project phù hợp.</p>
@endforelse</section>
@endif
{{ $projects->links() }}</x-layout>
