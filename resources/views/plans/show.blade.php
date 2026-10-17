<x-layout title="Chi tiết kế hoạch"><section class="card" data-page="plan-show"><p class="eyebrow">Kế hoạch · phiên bản {{ $plan->version_no }}</p><h1>{{ $plan->name }}</h1><div class="actions"><x-status :value="$plan->status" /><span class="badge">{{ $plan->is_active?'Đang áp dụng':($plan->applied_at?'Đã áp dụng trước đó':'Chưa áp dụng') }}</span><span>{{ $plan->data['source']==='ai'?'Đề xuất AI':'Thủ công / thuật toán local' }}</span></div><p>{{ $plan->reviewed_at?'Đã quyết định lúc '.$plan->reviewed_at->setTimezone($workspace->timezone)->format('d/m/Y H:i'):'Chưa có quyết định duyệt' }} · {{ $workspace->timezone }}</p>
<p class="notice">Duyệt chưa thay đổi công việc. Áp dụng chỉ sửa task chưa bắt đầu; không xóa task hoặc dependency cũ khi bỏ dòng khỏi đề xuất.</p>
<div class="actions"><a href="{{ route('workspaces.plans.index',[$workspace,'project_id'=>$project?->id]) }}">Danh sách</a>
@if($canRevise)
<a class="button secondary" href="{{ route('workspaces.plans.create',[$workspace,'project_id'=>$project?->id,'base_id'=>$plan->id]) }}">Sửa bằng phiên bản mới</a>
@endif
@if($canPreview&&!$plan->applied_at)
<a class="button" href="{{ route('workspaces.plans.preview',[$workspace,$plan]) }}">Xem trước tác động áp dụng</a>
@endif
</div>
<div class="actions review-actions">
@foreach($actions as $action=>$label)
@if($action!=='apply')
<form method="post" action="{{ route('workspaces.plans.action',[$workspace,$plan,$action]) }}" data-form="plan-{{ $action }}" data-confirm="{{ $label }} phiên bản {{ $plan->version_no }}?">@csrf<input type="hidden" name="expected_status" value="{{ $plan->status->value }}">
@if($action==='reject')<label for="reject-reason">Lý do từ chối</label><input id="reject-reason" name="reason" required maxlength="4000">@endif
<button class="{{ $action==='reject'?'danger':'' }}">{{ $label }}</button></form>
@endif
@endforeach
</div>
@if($plan->change_summary)<p>{{ $plan->change_summary }}</p>@endif
</section>
<section class="card"><h2>So sánh {{ $parent?'v'.$parent->version_no.' → v'.$plan->version_no:'đề xuất mới' }}</h2><p class="muted">“Bỏ khỏi đề xuất” không xóa công việc đã có. Các khung giờ/dependency được đối chiếu ở bảng bên dưới.</p>
@foreach($diff['rows'] as $row)
<article class="diff-row diff-{{ $row['kind'] }}"><h3>{{ $row['ref'] }} · {{ $row['title'] }}</h3><span class="badge">{{ ['added'=>'Thêm vào đề xuất','changed'=>'Có thay đổi','unchanged'=>'Giữ nguyên','omitted'=>'Bỏ khỏi đề xuất'][$row['kind']] }}</span>
@if($row['fields'])<dl class="diff-fields">
@foreach($row['fields'] as $field=>$change)
<dt>{{ ['sort_order'=>'Thứ tự trong nhóm','position'=>'Thứ tự','title'=>'Tiêu đề','description'=>'Mô tả','task_type'=>'Loại','parent_ref'=>'Nhóm cha','priority'=>'Ưu tiên','estimate_minutes'=>'Ước lượng (phút)','assignee_id'=>'Người thực hiện','reviewer_id'=>'Người duyệt'][$field] }}</dt><dd><span class="muted">{{ str_ends_with($field,'_id')?($users[$change['before']]??'Chưa phân công / không còn phù hợp'):\App\Support\UiLabels::text($change['before']??'Chưa có') }}</span> → <strong>{{ str_ends_with($field,'_id')?($users[$change['after']]??'Chưa phân công / không còn phù hợp'):\App\Support\UiLabels::text($change['after']??'Chưa có') }}</strong></dd>
@endforeach
</dl>@endif</article>
@endforeach
@foreach(['dependencies'=>'Quan hệ chờ FS','slots'=>'Khung giờ dự kiến'] as $key=>$label)
<h3>{{ $label }}</h3><div class="comparison"><div><h4>Phiên bản trước</h4>@foreach($diff[$key.'_before'] as $r)<p>{{ $key==='dependencies'?$r['predecessor'].' → '.$r['successor'].' · chờ '.$r['lag_minutes'].' phút':$r['task_ref'].' · '.$r['starts_at'].' → '.$r['ends_at'] }}</p>@endforeach</div><div><h4>Đề xuất này</h4>@forelse($diff[$key.'_after'] as $r)<p>{{ $key==='dependencies'?$r['predecessor'].' → '.$r['successor'].' · chờ '.$r['lag_minutes'].' phút':$r['task_ref'].' · '.$r['starts_at'].' → '.$r['ends_at'] }}</p>@empty<p>Chưa có.</p>@endforelse</div></div>
@endforeach
</section>
@if($canSchedule)
<section class="card"><h2>Xếp lịch local</h2><p>Tạo phiên bản mới theo giờ làm mặc định, tránh lịch bận và giữ FS/lag. Đây là thuật toán local, chưa áp dụng và không phải kết quả AI.</p><form method="post" action="{{ route('workspaces.plans.schedule',[$workspace,$plan]) }}">@csrf<label for="schedule-from">Ngày bắt đầu</label><input type="date" id="schedule-from" name="from" required value="{{ now($workspace->timezone)->format('Y-m-d') }}"><button>Tạo phiên bản xếp lịch local</button></form></section>
@endif
<details class="card"><summary>Dữ liệu JSON nâng cao</summary><pre>{{ json_encode($plan->data['payload'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>
@include('plans._impact')</x-layout>
