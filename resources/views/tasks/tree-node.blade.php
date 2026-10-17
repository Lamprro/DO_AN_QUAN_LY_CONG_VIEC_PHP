<ul>@forelse($nodes->get($parentId,collect()) as $node)@if(!in_array($node->id,$visited,true))<li data-tree-task-id="{{ $node->id }}"><p><a href="{{ route($routePrefix.'.show',[...$routeParams,$node]) }}">{{ $node->code }} — {{ $node->title }}</a> · {{ $node->task_type->value }} · {{ $node->status->value }} @if($node->archived_at) · Đã lưu trữ @endif</p>
@if($nodes->has($node->id))@include('tasks.tree-node',['parentId'=>$node->id,'visited'=>[...$visited,$node->id]])@endif</li>@endif
@empty<li>Chưa có công việc.</li>@endforelse</ul>
