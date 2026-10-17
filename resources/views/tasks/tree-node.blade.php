<ul class="task-tree">@forelse($nodes->get($parentId,collect()) as $node)
@if(!in_array($node->id,$visited,true))<li data-tree-task-id="{{ $node->id }}">
@if($node->task_type->value==='GROUP')<details open><summary>{{ $node->code }} — {{ $node->title }} · {{ \App\Support\UiLabels::text($node->status) }}</summary><a href="{{ route($routePrefix.'.show',[...$routeParams,$node]) }}">Mở nhóm việc</a>
@else<p><a href="{{ route($routePrefix.'.show',[...$routeParams,$node]) }}">{{ $node->code }} — {{ $node->title }}</a> <x-status :value="$node->task_type" /> <x-status :value="$node->status" /></p>
@endif
@if($nodes->has($node->id))@include('tasks.tree-node',['parentId'=>$node->id,'visited'=>[...$visited,$node->id]])@endif
@if($node->task_type->value==='GROUP')</details>@endif</li>@endif
@empty<li>Chưa có công việc phù hợp.</li>@endforelse</ul>
