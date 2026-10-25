<x-layout title="Đối chiếu thay đổi công việc">
@fragment('task-conflict')
<section class="card" data-page="task-conflict">
 <h1>Chưa lưu: công việc đã được cập nhật</h1>
 <p>Bản nhập dựa trên phiên bản #{{ $conflict['submitted_revision'] }}. Dữ liệu đang đối chiếu là phiên bản #{{ $conflict['revision'] }}. Chọn giá trị cho từng trường; mặc định giữ dữ liệu trên máy chủ. Các trường bạn không gửi được giữ nguyên.</p>
 <p class="muted">Hạn chót theo {{ $workspace->timezone }}. Bản đối chiếu có hiệu lực 20 phút, chỉ dùng trong phiên đăng nhập này. Quyền và phiên bản được kiểm tra lại khi lưu.</p>
 <form method="post" action="{{ route($routePrefix.'.resolve-edit',[...$routeParams,$task]) }}" data-form="task-conflict" data-panel-form data-dirty-form>
 @csrf<input type="hidden" name="conflict_token" value="{{ $conflict['token'] }}"><input type="hidden" name="expected_revision" value="{{ $conflict['revision'] }}">
 <div class="conflict-rows">@forelse($conflict['rows'] as $row)
 <fieldset class="card conflict-row"><legend>{{ $row['label'] }}</legend>
 <div class="quick-edit-grid"><div><strong>Bản bạn nhập</strong><p class="conflict-value">{{ $row['mine'] }}</p></div><div><strong>Máy chủ — #{{ $conflict['revision'] }}</strong><p class="conflict-value">{{ $row['server'] }}</p></div></div>
 <label for="choice_{{ $row['field'] }}">Giá trị sẽ lưu cho {{ $row['label'] }}</label><select id="choice_{{ $row['field'] }}" name="choices[{{ $row['field'] }}]" required><option value="server" selected>Giữ dữ liệu máy chủ</option><option value="mine">Dùng bản tôi nhập</option></select>
 </fieldset>
 @empty<p>Các trường đã gửi hiện trùng dữ liệu máy chủ. Xác nhận để tiếp tục với phiên bản hiện tại.</p>@endforelse</div>
 <div class="field"><label for="conflict_deadline_reason">Lý do đổi hạn (bắt buộc nếu chọn hạn khác máy chủ)</label><textarea id="conflict_deadline_reason" name="deadline_reason" maxlength="2000">{{ $conflict['deadline_reason'] }}</textarea></div>
 <div class="actions"><button type="submit">Lưu các lựa chọn đã đối chiếu</button><a class="button secondary" data-task-panel href="{{ route($routePrefix.'.edit',[...$routeParams,$task,'fresh'=>1]) }}">Bỏ bản nhập và tải lại dữ liệu hiện tại</a></div>
 </form>
</section>
@endfragment
</x-layout>
