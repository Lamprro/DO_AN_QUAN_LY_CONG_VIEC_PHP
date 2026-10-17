<x-layout title="Dashboard"><section class="card" data-page="dashboard"><a href="{{ route('workspaces.show',$workspace) }}">{{ $workspace->name }}</a><h1>Dashboard</h1>
<form method="GET" data-form="report-filter">@include('reports._filters')<button type="submit">Xem số liệu</button></form>
<div class="actions"><a href="{{ route('workspaces.reports.index',$workspace) }}">Các bản báo cáo của tôi</a><a href="{{ route('workspaces.ai-runs.index',$workspace) }}">Yêu cầu AI của tôi</a></div>
@if($canCreate)<form method="POST" action="{{ route('workspaces.reports.store',$workspace) }}" data-form="report-create">@csrf @include('reports._hidden')<button type="submit">Lưu bản báo cáo</button></form><p><a href="{{ route('workspaces.ai-runs.create',array_merge([$workspace],$filters)) }}">Yêu cầu AI nhận xét số liệu</a></p>@endif
</section>@include('reports._metrics')</x-layout>
