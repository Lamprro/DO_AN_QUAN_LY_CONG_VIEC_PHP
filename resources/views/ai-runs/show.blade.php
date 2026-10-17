<x-layout title="Trạng thái yêu cầu AI"><section class="card" data-page="ai-run-show" data-run-id="{{ $run->id }}"><h1>Trạng thái yêu cầu AI</h1><p data-ai-status="{{ $run->status->value }}">{{ $run->status->value }}</p><p style="white-space:pre-wrap">{{ $run->user_prompt }}</p><p>Bắt đầu: {{ $run->started_at?->setTimezone(auth()->user()->timezone)->format('d/m/Y H:i:s')??'Chưa bắt đầu' }} · Kết thúc: {{ $run->finished_at?->setTimezone(auth()->user()->timezone)->format('d/m/Y H:i:s')??'Chưa kết thúc' }}</p>
@if($run->error_message)<div class="notice errors" role="alert">{{ $run->error_message }}</div>
@endif
<p><a href="{{ route('workspaces.ai-runs.show',[$workspace,$run]) }}">Làm mới trạng thái</a> · <a href="{{ route('workspaces.ai-runs.index',$workspace) }}">Danh sách</a></p>
@can('manage',$run)
@if(in_array($run->status->value,['queued','running'],true))<form method="POST" action="{{ route('workspaces.ai-runs.cancel',[$workspace,$run]) }}" data-form="ai-run-cancel">
@csrf<input type="hidden" name="expected_status" value="{{ $run->status->value }}"><button type="submit">Hủy yêu cầu</button></form>
@endif
@if($run->status->value==='failed' && $calls->count()<config('ai.max_attempts'))<form method="POST" action="{{ route('workspaces.ai-runs.retry',[$workspace,$run]) }}" data-form="ai-run-retry">
@csrf<input type="hidden" name="expected_status" value="failed"><button type="submit">Thử lại</button></form>
@endif
@endcan</section>
<section class="card"><h2>Các lượt xử lý</h2>
@forelse($calls as $call)<article data-call-id="{{ $call->id }}"><p>Lượt {{ $call->attempt_no }} · {{ $call->provider }} / {{ $call->model }} · {{ $call->status->value }}</p>
@if($call->error_message)<p>{{ $call->error_message }}</p>
@endif
@if($run->status->value==='succeeded' && $call->status->value==='succeeded')<h3>Nhận xét từ AI</h3><p style="white-space:pre-wrap">{{ $call->response_payload['text']??'' }}</p>
@endif</article>
@empty<p>Chưa có lượt xử lý; không có kết quả AI.</p>
@endforelse</section>
@if($run->operation->value==='report')
@php($metrics=$run->context_snapshot['metrics'])
@include('reports._metrics')
@else<section class="card"><h2>Kết quả kế hoạch</h2>
@forelse($run->plans as $plan)<a href="{{ route('workspaces.plans.show',[$workspace,$plan]) }}">{{ $plan->name }} v{{ $plan->version_no }} (nháp)</a>
@empty<p>Chưa tạo bản nháp. Assistance chỉ trả tư vấn, không thay đổi dữ liệu.</p>
@endforelse</section>
@endif</x-layout>
