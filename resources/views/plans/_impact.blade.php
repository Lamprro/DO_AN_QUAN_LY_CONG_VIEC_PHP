<section class="card"><h2>Ảnh hưởng hiện tại</h2><p>Phân tích theo dữ liệu thật; không tự sửa trạng thái hoặc lịch.</p>
@forelse($impact as $item)<p><strong>{{ $item['task']->code }} — {{ $item['task']->title }}</strong>: {{ implode('; ',$item['reasons']) }}</p>
@empty<p>Không có cảnh báo trong phạm vi hiện tại.</p>
@endforelse</section>
