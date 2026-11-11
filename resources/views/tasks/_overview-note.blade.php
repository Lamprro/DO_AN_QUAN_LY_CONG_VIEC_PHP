@if($isPersonalOverview ?? false)
    <p class="notice">Tổng hợp việc riêng và công việc được giao cho bạn từ các project còn quyền xem. Mỗi công việc giữ nguyên nguồn; xem và cập nhật theo quyền tại nguồn. Bộ lọc chỉ thu hẹp danh sách này.</p>
    @if(($taskView ?? null) === 'tree')
        <p class="muted">Việc từ project được hiển thị độc lập, không kèm nhóm cha hay công việc của người khác. Tiến độ chỉ tính các việc trong danh sách này.</p>
    @endif
@endif
