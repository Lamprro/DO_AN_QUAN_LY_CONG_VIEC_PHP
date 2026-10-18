<section class="card"><h2>Hạn và điều kiện đang cản</h2><p>{{ $deadlineInsight['overdue']?'WORK đang quá hạn':'Không thuộc WORK quá hạn đang mở' }} · {{ $task->deadline_at?->setTimezone($workspace->timezone)->format('d/m/Y H:i')??'Chưa có hạn' }} · {{ $workspace->timezone }}</p>
@forelse($deadlineInsight['conditions'] as $condition)<p>{{ $condition }}</p>
@empty<p>Không phát hiện điều kiện cản từ blocker/FS/review/phân công.</p>
@endforelse<p>Đây là tình trạng hiện tại, không tự kết luận nguyên nhân trễ lịch sử.</p><details><summary>Lịch sử20 lần đổi hạn gần nhất</summary>
@forelse($deadlineInsight['history'] as $history)<article><p>{{ $history->actor?->name }} · {{ $history->created_at->setTimezone($workspace->timezone)->format('d/m/Y H:i:s') }}</p><p>{{ $history->data['before']['deadline_at']??'Chưa có' }} → {{ $history->data['after']['deadline_at']??'Chưa có' }} (nguồn UTC)</p><p>{{ $history->data['reason'] }}</p></article>
@empty<p>Chưa có sự kiện đổi hạn được ghi riêng. History task.updated cũ vẫn được giữ; không dựng bù quá khứ.</p>
@endforelse</details></section>
