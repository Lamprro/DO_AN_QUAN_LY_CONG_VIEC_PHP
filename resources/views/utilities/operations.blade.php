<x-layout title="Vận hành của tôi"><section class="card"><h1>Vận hành trong phạm vi được phép</h1><p>Chỉ tài khoản được cấp quyền vận hành mới mở được trang này. Chỉ tối đa100 AI run do chính bạn tạo, còn quyền nguồn; job tương ứng và50 reminder của bạn. Không phải tổng queue hệ thống. Không có heartbeat để kết luận worker đang sống.</p><p>Queue AI: {{ config('ai.queue') }} · Nhắc việc: work-management-reminders · Cần worker database thật. {{ $limited?'Danh sách job đạt giới hạn1000, có thể thiếu kết quả.':'' }}</p></section><section class="card"><h2>AI run/call</h2>
@forelse($runs as $run)<p><a href="{{ route('workspaces.ai-runs.show',[$workspace,$run]) }}">{{ \App\Support\UiLabels::text($run->operation) }} · {{ $run->id }}</a> · {{ \App\Support\UiLabels::text($run->status) }} · {{ $run->calls_count }} lượt · {{ $run->created_at->setTimezone(auth()->user()->timezone)->format('d/m/Y H:i:s') }}</p>
@empty<p>Không có run được phép xem.</p>
@endforelse</section><section class="card"><h2>Job AI đang lưu trong queue</h2>
@if(!$jobsAvailable)<p>Queue database khác connection mặc định: chưa đọc được job ở trang này; không thể kết luận worker hay queue trống.</p>@endif

@forelse($jobs as $job)<p>#{{ $job['id'] }} · {{ $job['attempts'] }} lần lấy · {{ $job['reserved']?'Đã được giữ để xử lý':'Chưa được giữ' }} · Có thể xử lý từ {{ \Carbon\CarbonImmutable::createFromTimestamp($job['available_at'])->setTimezone(auth()->user()->timezone)->format('d/m/Y H:i:s') }}</p>
@empty<p>Không có job pending tương ứng trong tập đọc; không suy ra run đã thành công.</p>
@endforelse</section><section class="card"><h2>Job AI lỗi đã lưu (tối đa1000 hàng nguồn)</h2>
@forelse($failedJobs as $job)<p>#{{ $job['id'] }} · {{ $job['failed_at'] }} UTC · Không hiển thị exception/payload. Mở run để xem trạng thái và thử lại theo số lượt còn lại.</p>
@empty<p>Không có dead-letter phù hợp trong tập đã đọc.</p>
@endforelse<h2>Reminder ledger</h2>
@forelse($reminders as $r)<p>{{ $r->kind }} · {{ $r->status }} · {{ $r->local_date->format('d/m/Y') }} · {{ $r->skip_reason }}</p>
@empty<p>Không có lời nhắc được phép đọc.</p>
@endforelse</section></x-layout>
