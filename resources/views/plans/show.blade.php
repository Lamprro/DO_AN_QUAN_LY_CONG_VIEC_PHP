<x-layout title="Chi tiết kế hoạch"><section class="card" data-page="plan-show"><h1>{{ $plan->name }} — v{{ $plan->version_no }}</h1><p>{{ $plan->status->value }} · {{ $plan->data['source'] }} · {{ $plan->is_active?'Đang áp dụng':'Chưa hoạt động' }}</p><div class="actions"><a href="{{ route('workspaces.plans.index',[$workspace,'project_id'=>$project?->id]) }}">Danh sách</a>
@if(app(\App\Services\PlanService::class)->allowed(auth()->user(),$workspace,$project,'plan.update') && app(\App\Services\PlanService::class)->allowed(auth()->user(),$workspace,$project,'plan.create'))
<a href="{{ route('workspaces.plans.create',[$workspace,'project_id'=>$project?->id,'base_id'=>$plan->id]) }}">Sửa bằng phiên bản mới</a>
@endif
</div><p>Áp dụng chỉ sửa task TODO; giữ task đã bắt đầu. Slot chỉ được đặt cho chính người áp dụng và không được trùng lịch. Dependency hiện có được giữ nguyên; danh sách không có nghĩa là xóa dữ liệu cũ.</p>
@foreach(['propose'=>'Đề xuất','approve'=>'Duyệt','reject'=>'Từ chối','apply'=>'Áp dụng'] as $action=>$label)
@php($required=match($action){'propose'=>'draft','approve','reject'=>'proposed',default=>'approved'})
@php($permission=match($action){'propose'=>'plan.update','approve','reject'=>'plan.approve',default=>'plan.apply'})
@if(!($action==='apply' && $plan->applied_at) && $plan->status->value===$required && app(\App\Services\PlanService::class)->allowed(auth()->user(),$workspace,$project,$permission))<form data-confirm="Xác nhận thao tác kế hoạch này?" method="post" action="{{ route('workspaces.plans.action',[$workspace,$plan,$action]) }}">
@csrf<input type="hidden" name="expected_status" value="{{ $plan->status->value }}">
@if($action==='reject')<label>Lý do<input name="reason" required maxlength="4000"></label>
@endif<button>{{ $label }}</button></form>
@endif
@endforeach
@if($plan->change_summary)<p>{{ $plan->change_summary }}</p>
@endif<div class="table-wrap"><table><thead><tr><th>Ref</th><th>Task</th><th>Loại</th><th>Phút</th></tr></thead><tbody>
@foreach($plan->data['payload']['tasks'] as $row)<tr><td>{{ $row['ref'] }}</td><td>{{ $row['title'] }}</td><td>{{ $row['task_type'] }}</td><td>{{ $row['estimate_minutes']??'Chưa ước lượng' }}</td></tr>
@endforeach</tbody></table></div>
@if(app(\App\Services\PlanService::class)->allowed(auth()->user(),$workspace,$project,'plan.update'))<form method="post" action="{{ route('workspaces.plans.schedule',[$workspace,$plan]) }}">
@csrf<label>Ngày bắt đầu xếp lịch<input type="date" name="from" required value="{{ now($workspace->timezone)->format('Y-m-d') }}"></label><button>Tạo phiên bản xếp lịch local</button></form>
@endif<details open><summary>Dữ liệu phiên bản này</summary><pre>{{ json_encode($plan->data['payload'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>
@if($parent)<details><summary>So sánh với v{{ $parent->version_no }} (bản trước)</summary><pre>{{ json_encode($parent->data['payload'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>
@endif</section>
@include('plans._impact')</x-layout>
