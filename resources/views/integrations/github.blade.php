<x-layout title="GitHub logs"><section class="card"><h1>GitHub logs — {{ $project->name }}</h1><p>Chỉ nhận webhook có chữ ký, không thay trạng thái task. Trong commit dùng [MÃ_TASK] để liên kết activity. Secret lưu mã hóa và không hiển thị lại.</p><p>Webhook URL: {{ route('github.webhook',$project) }}</p><p>Tích hợp toàn cục: {{ config('work_management.integrations_enabled')?'bật':'tắt' }}; kết nối: {{ $connection?->status->value ?? 'chưa cấu hình' }}</p>
@if(app(\App\Support\ProjectAccess::class)->allows(auth()->user(),$project,'integration.manage'))<form method="post" action="{{ route('workspaces.projects.github.save',[$workspace,$project]) }}">
@csrf<label>Repository ID (số)<input name="repository_id" required value="{{ $connection?->account_ref }}"></label><label>Webhook secret mới (để trống để giữ)<input name="webhook_secret" type="password" minlength="32" autocomplete="new-password"></label><label>Trạng thái<select name="enabled"><option value="0">Tắt</option><option value="1"
@selected($connection?->status->value==='active')>Bật</option></select></label><button>Lưu cấu hình</button></form>
@endif</section><section class="card"><h2>Lượt nhận</h2><p>Content type khi cấu hình webhook: application/json; chọn sự kiện push. Chỉ bật connection không xác minh được webhook public.</p>
@if($latestDelivery)<p data-latest-delivery>Lần nhận gần nhất: {{ $latestDelivery->received_at->setTimezone(auth()->user()->timezone)->format('d/m/Y H:i:s') }} · {{ auth()->user()->timezone }} · {{ \App\Support\UiLabels::text($latestDelivery->status) }}. Đây là log đã lưu, không phải kết quả kiểm tra mạng ở lần mở trang này.</p>
@else<p>Chưa có lượt nhận được lưu cho project này. Chưa xác minh kết nối GitHub.</p>
@endif
@foreach($logs as $log)<p>{{ $log->received_at->setTimezone(auth()->user()->timezone)->format('d/m/Y H:i:s') }} · {{ $log->delivery_id }} · {{ $log->summary }} · {{ \App\Support\UiLabels::text($log->status) }}</p>
@endforeach{{ $logs->links() }}</section></x-layout>
