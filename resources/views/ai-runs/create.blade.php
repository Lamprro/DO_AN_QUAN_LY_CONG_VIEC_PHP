<x-layout title="Yêu cầu nhận xét AI"><section class="card" data-page="ai-run-create"><h1>Yêu cầu nhận xét AI</h1>
@include('ai-runs._configuration')<p>Yêu cầu chỉ dùng bản chụp số liệu được phép xem. Không tự đổi task, lịch hay kế hoạch.</p>
<form method="POST" action="{{ route('workspaces.ai-runs.store',$workspace) }}" data-form="ai-run-create">
@csrf<input type="hidden" name="idempotency_token" value="{{ old('idempotency_token',$token) }}">
@include('reports._filters')<x-textarea name="user_prompt" label="Nội dung muốn nhận xét" required maxlength="4000"/><button type="submit">Lưu yêu cầu</button></form><a href="{{ route('workspaces.ai-runs.index',$workspace) }}">Các yêu cầu của tôi</a></section></x-layout>
