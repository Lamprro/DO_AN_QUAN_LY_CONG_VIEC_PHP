<x-layout title="Projects"><h1>Projects — {{ $workspace->name }}</h1>
<div class="actions"><a href="{{ route('workspaces.show',$workspace) }}">Về workspace</a>
@if($canCreate)<a class="button" href="{{ route('workspaces.projects.create',$workspace) }}">Tạo project</a>@endif</div>
<form class="card" method="GET" action="{{ route('workspaces.projects.index',$workspace) }}" data-form="project-search">
<x-input name="q" label="Tìm tên project" :value="$filters['q'] ?? ''" />
<label for="status">Trạng thái</label><select name="status" id="status"><option value="">Tất cả</option>@foreach(\App\Enums\ProjectStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status']??'')===$s->value)>{{ $s->value }}</option>@endforeach</select>
@include('shared.list-controls',['sortOptions'=>['created_at'=>'Ngày tạo','name'=>'Tên','deadline'=>'Hạn hoàn thành']])
<button type="submit">Lọc</button></form>
<section class="grid" data-page="projects-index">@forelse($projects as $project)<article class="card"><h2><a href="{{ route('workspaces.projects.show',[$workspace,$project]) }}">{{ $project->name }}</a></h2><p>{{ $project->status->value }} · {{ $project->tasks_count }} task @if($project->archived_at) · Đã lưu trữ @endif</p><p>Hạn: {{ $project->deadline?->format('d/m/Y') ?? 'Chưa có' }}</p></article>@empty<p>Chưa có project phù hợp.</p>@endforelse</section>{{ $projects->links() }}</x-layout>
