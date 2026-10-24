@if($collaborationReady)
<section class="card" id="task-watchers"><h2>Người theo dõi</h2><p>Người theo dõi nhận cập nhật khi còn quyền xem. Người thực hiện chính và giờ báo cáo vẫn là một nguồn duy nhất.</p>
@if(!$task->archived_at)<form method="post" action="{{ route('workspaces.tasks.watchers.store',[$workspace,$task->id]) }}" data-form="watcher-add">@csrf
@can('link',$task)<label for="watcher-user">Thêm người theo dõi</label><select id="watcher-user" name="user_id">@foreach($collaborationPeople as $m)<option value="{{ $m->user_id }}">{{ $m->user->name }}</option>@endforeach</select>@else<input type="hidden" name="user_id" value="{{ auth()->id() }}">@endcan<button>Theo dõi</button></form>@endif
<ul>@forelse($watchers as $w)<li>{{ $w->user->name }} @if($w->user_id===auth()->id()||auth()->user()->can('link',$task))<form class="inline" method="post" action="{{ route('workspaces.tasks.watchers.destroy',[$workspace,$task->id,$w->user_id]) }}" data-form="watcher-remove-{{ $w->user_id }}">@csrf @method('DELETE')<button class="secondary">Dừng theo dõi</button></form>@endif</li>@empty<li>Chưa có người theo dõi.</li>@endforelse</ul></section>

@endif
