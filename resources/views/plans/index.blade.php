<x-layout title="Kế hoạch"><section class="card" data-page="plans-index"><h1>Kế hoạch {{ $project?->name }}</h1><div class="actions">
@if(app(\App\Services\PlanService::class)->allowed(auth()->user(),$workspace,$project,'plan.create'))
<a href="{{ route('workspaces.planning-ai.create',[$workspace,'project_id'=>$project?->id]) }}">AI / trợ lý blocker</a><a href="{{ route('workspaces.plans.create',[$workspace,'project_id'=>$project?->id]) }}">Tạo nháp</a>
@endif
<a href="{{ route('workspaces.plans.impact',[$workspace,'project_id'=>$project?->id]) }}">Phân tích ảnh hưởng</a></div><div class="table-wrap"><table><thead><tr><th>Tên</th><th>Phiên bản</th><th>Trạng thái</th></tr></thead><tbody>
@foreach($plans as $plan)<tr><td><a href="{{ route('workspaces.plans.show',[$workspace,$plan]) }}">{{ $plan->name }}</a></td><td>{{ $plan->version_no }}</td><td>{{ $plan->status->value }} {{ $plan->is_active?'(đang áp dụng)':'' }}</td></tr>
@endforeach</tbody></table></div>{{ $plans->links() }}</section></x-layout>
