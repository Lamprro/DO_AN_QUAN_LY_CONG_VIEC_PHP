<x-layout title="Cây công việc"><h1>Cây công việc — {{ $project?->name ?? $workspace->name }}</h1>
<a href="{{ route($routePrefix.'.index',$routeParams) }}">Danh sách công việc</a>
<section class="card"><p>Tiến độ WORK lá: {{ $progress['percent'] }}% · {{ $progress['done'] }}/{{ $progress['total'] }} {{ $progress['method']==='estimate'?'phút ước lượng':'công việc' }}.</p><p class="muted">Không tính công việc đã hủy. {{ $progress['method']==='count'?'Dùng số lượng vì có công việc chưa có ước lượng dương.':'' }}</p></section>
<section class="card" data-page="task-tree">@include('tasks.tree-node',['parentId'=>'root','visited'=>[]])</section></x-layout>
