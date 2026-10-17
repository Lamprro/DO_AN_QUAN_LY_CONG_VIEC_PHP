<x-layout title="GitHub logs"><section class="card"><h1>GitHub logs — {{ $project->name }}</h1><p>Chỉ nhận webhook có chữ ký, không thay trạng thái task. Trong commit dùng [MÃ_TASK] để liên kết activity. Secret lưu mã hóa và không hiển thị lại.</p><p>Webhook URL: {{ route('github.webhook',$project) }}</p><p>Tích hợp toàn cục: {{ config('work_management.integrations_enabled')?'bật':'tắt' }}; kết nối: {{ $connection?->status->value ?? 'chưa cấu hình' }}</p>
@if(app(\App\Support\ProjectAccess::class)->allows(auth()->user(),$project,'integration.manage'))<form method="post" action="{{ route('workspaces.projects.github.save',[$workspace,$project]) }}">
@csrf<label>Repository ID (số)<input name="repository_id" required value="{{ $connection?->account_ref }}"></label><label>Webhook secret mới (để trống để giữ)<input name="webhook_secret" type="password" minlength="32" autocomplete="new-password"></label><label>Trạng thái<select name="enabled"><option value="0">Tắt</option><option value="1"
@selected($connection?->status->value==='active')>Bật</option></select></label><button>Lưu cấu hình</button></form>
@endif</section><section class="card"><h2>Lượt nhận</h2>
@foreach($logs as $log)<p>{{ $log->received_at }} · {{ $log->delivery_id }} · {{ $log->summary }} · {{ $log->status->value }}</p>
@endforeach{{ $logs->links() }}</section></x-layout>
